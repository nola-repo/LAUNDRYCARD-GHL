<?php

namespace App\Services;

use App\Exceptions\Integration\AuthorizationException;
use App\Exceptions\Integration\IntegrationException;
use App\Exceptions\Integration\RetryableException;
use App\Models\ContactMapping;
use App\Models\GhlConnection;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\SyncAttempt;
use App\Support\Normalizer;
use App\Support\Sanitizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Throwable;

/**
 * LaundryCard record -> GHL contact (one-way). Idempotent, race-safe and
 * incapable of choosing a destination other than the verified mapping.
 */
class ContactSyncService
{
    public function __construct(
        private BranchMappingService $mappings,
        private CustomerMatchingService $matching,
        private HighLevelClient $ghl,
        private IntegrationLogger $logger,
    ) {
    }

    /**
     * @return array{outcome:string,status:string,detail:?string,contact_id:?string}
     *
     * @throws RetryableException after recording the attempt, so the queue can back off
     */
    public function process(Rec $record, ?string $correlation = null, ?int $userId = null, ?int $syncRunId = null): array
    {
        $correlation = $this->logger->newCorrelation($correlation);
        if ($record->exists) {
            $record->refresh();
        }

        // Idempotency: an already-synced record is never sent again.
        if ($record->isSynced() && $record->contactMapping()->exists()) {
            return $this->result('already_synced', $record->processing_status, null, $record->contactMapping->ghl_contact_id);
        }

        if (! config('integration.sync.enabled')) {
            $record->update(['processing_status' => Rec::HELD, 'hold_reason' => 'sync_disabled']);

            return $this->result('held_sync_disabled', Rec::HELD, 'sync_disabled', null);
        }

        // Defense in depth: no queue job or manual retry may bypass the verified
        // current-balance eligibility decision made by discovery.
        if ($record->eligibility_status !== Rec::BALANCE_ELIGIBLE || $record->balance === null || (float) $record->balance <= 0) {
            $reason = $record->eligibility_reason ?? 'balance_not_verified';
            if (! $record->isSynced()) {
                $record->update(['processing_status' => Rec::HELD, 'hold_reason' => $reason]);
            }

            return $this->result('held_balance_ineligible', Rec::HELD, $reason, null);
        }

        // Atomic claim => only one worker can process a record at a time.
        $originalStatus = $record->processing_status; // capture before claim overwrites it
        $claimed = Rec::whereKey($record->id)
            ->whereIn('processing_status', [Rec::PENDING, Rec::QUEUED, Rec::HELD, Rec::FAILED])
            ->where('eligibility_status', Rec::BALANCE_ELIGIBLE)
            ->where('balance', '>', 0)
            ->update(['processing_status' => Rec::PROCESSING, 'last_attempt_at' => now(), 'attempt_count' => \DB::raw('attempt_count + 1')]);
        if (! $claimed) {
            $fresh = $record->fresh();
            if ($fresh && ($fresh->eligibility_status !== Rec::BALANCE_ELIGIBLE || $fresh->balance === null || (float) $fresh->balance <= 0)) {
                $fresh->update(['processing_status' => Rec::HELD, 'hold_reason' => $fresh->eligibility_reason ?? 'balance_not_verified']);

                return $this->result('held_balance_ineligible', Rec::HELD, $fresh->eligibility_reason, null);
            }

            return $this->result('skipped_not_claimable', $fresh?->processing_status ?? Rec::FAILED, 'Record is being processed or is not eligible.', null);
        }
        $record->refresh();

        $attempt = SyncAttempt::create([
            'sync_run_id' => $syncRunId, 'source_record_id' => $record->id, 'correlation_id' => $correlation,
            'attempt_no' => $record->attempt_count, 'status' => 'failed', 'stage' => 'start', 'ghl_connection_id' => null,
            'triggered_by' => $userId, 'started_at' => now(),
        ]);
        $storeId = $record->store->store_id;
        $base = ['store_id' => $storeId, 'source_record_id' => $record->id, 'retry_count' => max(0, $record->attempt_count - 1)];

        $connection = null;
        try {
            // 1. Route strictly by verified mapping.
            $decision = $this->mappings->resolve($record->store);
            if (! $decision['routable']) {
                $this->logger->log('sync.route', $base + ['outcome' => 'held', 'level' => 'warning', 'message' => 'Not routable: '.$decision['reason']]);

                return $this->hold($record, $attempt, 'route', $decision['reason']);
            }
            $connection = $decision['connection'];
            $base['ghl_location_id'] = $connection->location_id;
            $attempt->update(['ghl_connection_id' => $connection->id, 'stage' => 'route']);
            $this->logger->log('sync.route', $base + ['outcome' => 'routed', 'message' => 'Routed via verified mapping.']);

            // 2. Preconditions.
            $email = Normalizer::email($record->customer_email);
            $phone = Normalizer::phone($record->customer_phone);
            if (! $email && ! $phone) {
                return $this->finish($record, $attempt, Rec::SKIPPED, 'skipped', 'no_contact_identifier', 'no_contact_identifier', $base);
            }
            $scopes = $connection->scopes ?? [];
            if (! in_array('contacts.write', $scopes, true) || ! in_array('contacts.readonly', $scopes, true)) {
                return $this->hold($record, $attempt, 'scope', 'missing_scope');
            }

            // 3. Existing source->contact link wins over any lookup — but only when the
            //    record was genuinely synced. If it FAILED we discard the stale link
            //    and re-search so the mapping is corrected if GHL already has the contact.
            $link = ($originalStatus !== Rec::FAILED) ? $record->contactMapping : null;
            $existing = null;
            $matchedOn = 'mapping';
            if ($link && $link->location_id === $connection->location_id) {
                $existing = $this->ghl->getContact($connection, $link->ghl_contact_id);
            }


            // 4. Look for an existing contact.
            if (! $existing) {
                $attempt->update(['stage' => 'match']);
                $match = $this->matching->match($connection, $record);
                $this->logger->log('sync.match', $base + ['outcome' => $match['result'], 'message' => $match['reason'] ?? ($match['matched_on'] ? 'matched on '.$match['matched_on'] : 'no existing contact')]);
                if ($match['result'] === CustomerMatchingService::CONFLICT) {
                    return $this->hold($record, $attempt, 'match', 'conflicting_contacts');
                }
                $existing = $match['contact'];
                $matchedOn = $match['matched_on'];
            }

            // 5. Create or (safely) update.
            if ($existing) {
                $attempt->update(['stage' => 'update']);
                [$action, $contactId] = $this->updateSafely($connection, $existing, $record, $email, $phone);
            } else {
                $attempt->update(['stage' => 'create']);
                $created = $this->ghl->createContact($connection, $this->payload($record, $email, $phone, $storeId));
                [$action, $contactId] = ['created', $created['id']];
            }

            // 6. Persist the link (unique source_record_id + idempotency_key).
            $key = hash('sha256', $connection->location_id.'|'.$storeId.'|'.$record->card_number);
            try {
                ContactMapping::updateOrCreate(
                    ['source_record_id' => $record->id],
                    ['ghl_connection_id' => $connection->id, 'location_id' => $connection->location_id, 'ghl_contact_id' => $contactId,
                        'action' => $action, 'idempotency_key' => $key, 'synced_at' => now()]
                );
            } catch (UniqueConstraintViolationException) {
                // A concurrent worker stored the same link: equivalent outcome.
            }
            $record->update([
                'ghl_connection_id' => $connection->id, 'destination_location_id' => $connection->location_id,
                'synced_at' => now(), 'hold_reason' => null,
            ]);
            $mapping = $decision['mapping'];
            $mapping->update(['last_synced_at' => now()]);

            $status = $action === 'created' ? Rec::SYNCED_CREATED : Rec::SYNCED_UPDATED;

            return $this->finish($record, $attempt, $status, 'success', $action, null, $base, 'success', $contactId);
        } catch (RetryableException $e) {
            $this->failed($record, $attempt, $e, true, $base);
            throw $e;
        } catch (AuthorizationException $e) {
            // Revoked/expired credentials: park until reconnection (auto-released by dispatchPending).
            $this->failed($record, $attempt, $e, false, $base);
            $record->update(['processing_status' => Rec::HELD, 'hold_reason' => 'connection_unusable']);
            $attempt->update(['status' => 'held', 'retry_eligible' => true]);

            return $this->result('held', Rec::HELD, 'connection_unusable', null);
        } catch (IntegrationException $e) {
            $this->failed($record, $attempt, $e, false, $base);

            return $this->result('failed', Rec::FAILED, Sanitizer::string($e->getMessage(), 200), null);
        } catch (Throwable $e) {
            $this->failed($record, $attempt, $e, false, $base);

            return $this->result('failed', Rec::FAILED, 'Unexpected error: '.class_basename($e), null);
        }
    }

    private function payload(Rec $record, ?string $email, ?string $phone, string $storeId): array
    {
        [$first, $last] = Normalizer::splitName($record->customer_name);

        return array_filter([
            'firstName' => $first, 'lastName' => $last, 'email' => $email, 'phone' => $phone,
            'source' => config('integration.ghl.contact_source'),
            'tags' => config('integration.ghl.contact_tags'),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** Fill blanks only; never overwrite data a human may have curated in GHL. */
    private function updateSafely(GhlConnection $connection, array $existing, Rec $record, ?string $email, ?string $phone): array
    {
        $id = $existing['id'];
        $desired = $this->payload($record, $email, $phone, '');
        $changes = [];
        foreach (['firstName', 'lastName', 'email', 'phone'] as $f) {
            if (isset($desired[$f]) && empty($existing[$f])) {
                $changes[$f] = $desired[$f];
            }
        }
        $tags = array_values(array_unique(array_merge((array) ($existing['tags'] ?? []), (array) ($desired['tags'] ?? []))));
        if ($tags !== array_values((array) ($existing['tags'] ?? []))) {
            $changes['tags'] = $tags; // PUT replaces tags, so send the union
        }
        if (! $changes) {
            return ['linked', $id];
        }
        $this->ghl->updateContact($connection, $id, $changes);

        return ['updated', $id];
    }

    private function hold(Rec $record, SyncAttempt $attempt, string $stage, string $reason): array
    {
        $record->update(['processing_status' => Rec::HELD, 'hold_reason' => $reason]);
        $attempt->update(['status' => 'held', 'stage' => $stage, 'outcome' => $reason, 'retry_eligible' => in_array($reason, \App\Services\CustomerDiscoveryService::RELEASABLE_HOLDS, true), 'finished_at' => now()]);

        return $this->result('held', Rec::HELD, $reason, null);
    }

    private function finish(Rec $record, SyncAttempt $attempt, string $status, string $attemptStatus, string $outcome, ?string $hold, array $base, ?string $logOutcome = null, ?string $contactId = null): array
    {
        $record->update(['processing_status' => $status, 'hold_reason' => $hold]);
        $attempt->update(['status' => $attemptStatus, 'outcome' => $outcome, 'stage' => 'done', 'retry_eligible' => false, 'finished_at' => now()]);
        $this->logger->log('sync.result', $base + ['outcome' => $outcome, 'message' => 'Record processed.']);

        return $this->result($outcome, $status, $hold, $contactId);
    }

    private function failed(Rec $record, SyncAttempt $attempt, Throwable $e, bool $retryable, array $base): void
    {
        $msg = Sanitizer::string($e->getMessage(), 300);
        $record->update(['processing_status' => Rec::FAILED]);
        $attempt->update([
            'status' => 'failed', 'error_message' => $msg, 'retry_eligible' => $retryable,
            'http_status' => $e instanceof IntegrationException ? $e->httpStatus : null, 'finished_at' => now(),
        ]);
        $this->logger->log('sync.result', $base + [
            'level' => 'error', 'outcome' => 'failure', 'message' => $msg,
            'http_status' => $e instanceof IntegrationException ? $e->httpStatus : null,
        ]);
    }

    private function result(string $outcome, string $status, ?string $detail, ?string $contactId): array
    {
        return ['outcome' => $outcome, 'status' => $status, 'detail' => $detail, 'contact_id' => $contactId];
    }
}
