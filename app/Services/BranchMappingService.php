<?php

namespace App\Services;

use App\Models\BranchMapping;
use App\Models\GhlConnection;
use App\Models\LaundrycardStore;
use App\Models\MappingAuditLog;
use App\Models\User;
use App\Support\Normalizer;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * LaundryCard Store ID -> verified GHL Location ID -> active OAuth connection.
 * Routing is ONLY ever decided from a verified mapping row; name similarity is
 * used purely to suggest candidates to an administrator.
 */
class BranchMappingService
{
    public const NO_MAPPING = 'no_mapping';
    public const NOT_VERIFIED = 'mapping_not_verified';
    public const CONNECTION_UNUSABLE = 'connection_unusable';

    public function __construct(private IntegrationLogger $logger)
    {
    }

    /** Discover stores through the API; register new ones as unmapped; flag vanished ones. */
    public function refreshStores(LaundryCardClient $client): array
    {
        $stores = $client->stores();
        $seen = [];
        $new = 0;

        foreach ($stores as $s) {
            $seen[] = $s['store_id'];
            $store = LaundrycardStore::firstOrNew(['store_id' => $s['store_id']]);
            $isNew = ! $store->exists;
            $store->fill(array_filter([
                'name' => $s['name'], 'address' => $s['address'], 'city' => $s['city'],
                'state' => $s['state'], 'zip_code' => $s['zip_code'], 'timezone' => $s['timezone'],
            ], fn ($v) => $v !== null));
            $store->live_enabled = $s['live_enabled'];
            $store->status = 'active';
            $store->last_seen_at = now();
            $store->first_seen_at ??= now();
            $store->save();

            if ($isNew) {
                $new++;
                $this->logger->log('stores.discovered', [
                    'outcome' => 'unmapped', 'store_id' => $store->store_id,
                    'message' => "New LaundryCard store {$store->store_id} registered; branch mapping required.",
                ]);
            }
        }

        $missing = LaundrycardStore::where('status', 'active')->whereNotIn('store_id', $seen)->get();
        foreach ($missing as $m) {
            $m->update(['status' => 'missing']);
            $this->logger->log('stores.missing', ['level' => 'warning', 'store_id' => $m->store_id, 'message' => 'Store no longer returned by API.']);
        }

        return ['total' => count($stores), 'new' => $new, 'missing' => $missing->count()];
    }

    /** Suggest (never apply) a connection for a store based on name similarity. */
    public function suggest(LaundrycardStore $store, $connections = null): ?array
    {
        $connections ??= $this->unassignedConnections();
        $best = null;
        foreach ($connections as $c) {
            $score = $this->similarity((string) $store->name, (string) $c->location_name);
            if ($score >= 40 && (! $best || $score > $best['score'])) {
                $best = ['connection' => $c, 'score' => $score];
            }
        }

        return $best;
    }

    public function similarity(string $a, string $b): int
    {
        $stop = ['the', 'of', 'llc', 'inc', 'laundromat', 'suds'];
        $tok = fn (string $s) => array_values(array_diff(preg_split('/[^a-z0-9]+/', strtolower($s), -1, PREG_SPLIT_NO_EMPTY), $stop));
        $ta = $tok($a);
        $tb = $tok($b);
        if (! $ta || ! $tb) {
            return 0;
        }
        $jaccard = count(array_intersect($ta, $tb)) / count(array_unique(array_merge($ta, $tb))) * 100;
        similar_text(implode(' ', $ta), implode(' ', $tb), $pct);

        return (int) round(max($jaccard, $pct * 0.8));
    }

    /** Create (or reuse) a PROPOSED mapping. Does not enable synchronisation. */
    public function assign(LaundrycardStore $store, GhlConnection $connection, ?User $user, string $origin = 'manual', ?int $score = null): BranchMapping
    {
        return DB::transaction(function () use ($store, $connection, $user, $origin, $score) {
            $other = BranchMapping::where('ghl_connection_id', $connection->id)->where('status', BranchMapping::VERIFIED)
                ->where('laundrycard_store_id', '!=', $store->id)->first();
            if ($other) {
                throw new DomainException('That GHL location is already verified for another LaundryCard store. Disable that mapping first.');
            }

            $current = BranchMapping::where('laundrycard_store_id', $store->id)->where('status', BranchMapping::VERIFIED)->first();
            if ($current && $current->ghl_connection_id === $connection->id) {
                throw new DomainException('This pair is already verified.');
            }
            // Changing an already verified store: the old mapping stops routing immediately.
            if ($current) {
                $this->disable($current, $user, 'replaced by new assignment');
            }

            // Retire any older unverified proposals for the store so there is a single pending proposal.
            BranchMapping::where('laundrycard_store_id', $store->id)->where('status', BranchMapping::PROPOSED)
                ->where('ghl_connection_id', '!=', $connection->id)
                ->update(['status' => BranchMapping::DISABLED, 'disabled_at' => now(), 'disabled_by' => $user?->id]);

            $mapping = BranchMapping::where('laundrycard_store_id', $store->id)
                ->where('ghl_connection_id', $connection->id)->where('status', BranchMapping::PROPOSED)->first()
                ?? BranchMapping::create([
                    'laundrycard_store_id' => $store->id, 'ghl_connection_id' => $connection->id,
                    'status' => BranchMapping::PROPOSED, 'origin' => $origin, 'suggestion_score' => $score,
                    'created_by' => $user?->id,
                ]);

            $this->audit($mapping, $current ? 'changed' : 'assigned', $current ? ['ghl_connection_id' => $current->ghl_connection_id] : null, ['ghl_connection_id' => $connection->id, 'status' => 'proposed'], $user);

            return $mapping;
        });
    }

    public function verify(BranchMapping $mapping, ?User $user): BranchMapping
    {
        if ($mapping->status !== BranchMapping::PROPOSED) {
            throw new DomainException('Only a proposed mapping can be verified.');
        }
        $connection = $mapping->connection;
        if ($connection->status !== GhlConnection::ACTIVE) {
            throw new DomainException('The GHL connection is not active; reconnect it before verifying.');
        }

        try {
            DB::transaction(function () use ($mapping, $user) {
                $mapping->update([
                    'status' => BranchMapping::VERIFIED,
                    'verified_at' => now(), 'verified_by' => $user?->id,
                    'active_store_key' => $mapping->laundrycard_store_id,
                    'active_connection_key' => $mapping->ghl_connection_id,
                ]);
                $this->audit($mapping, 'verified', ['status' => 'proposed'], ['status' => 'verified'], $user);
            });
        } catch (QueryException $e) {
            throw new DomainException('Another verified mapping already uses this store or location.');
        }

        return $mapping->refresh();
    }

    /** Disable keeps the row (history + synced records) but stops routing. */
    public function disable(BranchMapping $mapping, ?User $user, string $reason = 'manual'): BranchMapping
    {
        if ($mapping->status === BranchMapping::DISABLED) {
            return $mapping;
        }
        $old = ['status' => $mapping->status];
        $mapping->update([
            'status' => BranchMapping::DISABLED, 'disabled_at' => now(), 'disabled_by' => $user?->id,
            'active_store_key' => null, 'active_connection_key' => null,
        ]);
        $this->audit($mapping, 'disabled', $old, ['status' => 'disabled', 'reason' => $reason], $user);

        return $mapping;
    }

    /**
     * Routing decision for a store. The ONLY source of truth for destination location.
     *
     * @return array{routable:bool,reason:?string,mapping:?BranchMapping,connection:?GhlConnection}
     */
    public function resolve(LaundrycardStore $store): array
    {
        $mapping = BranchMapping::with('connection')->where('laundrycard_store_id', $store->id)->where('status', BranchMapping::VERIFIED)->first();
        if (! $mapping) {
            $hasProposal = BranchMapping::where('laundrycard_store_id', $store->id)->where('status', BranchMapping::PROPOSED)->exists();

            return ['routable' => false, 'reason' => $hasProposal ? self::NOT_VERIFIED : self::NO_MAPPING, 'mapping' => null, 'connection' => null];
        }
        $conn = $mapping->connection;
        if (! $conn || ! $conn->isUsable()) {
            return ['routable' => false, 'reason' => self::CONNECTION_UNUSABLE, 'mapping' => $mapping, 'connection' => $conn];
        }

        return ['routable' => true, 'reason' => null, 'mapping' => $mapping, 'connection' => $conn];
    }

    public function unmappedStores()
    {
        return LaundrycardStore::where('status', 'active')->whereDoesntHave('activeMapping')->orderBy('store_id')->get();
    }

    /** Authorised locations that are not part of a verified mapping. */
    public function unassignedConnections()
    {
        return GhlConnection::whereDoesntHave('activeMapping')->orderBy('location_name')->get();
    }

    public function storeByAnyId(string|int $id): ?LaundrycardStore
    {
        return LaundrycardStore::where('store_id', Normalizer::storeId($id))->first();
    }

    private function audit(BranchMapping $m, string $action, ?array $old, ?array $new, ?User $user): void
    {
        MappingAuditLog::create([
            'branch_mapping_id' => $m->id, 'laundrycard_store_id' => $m->laundrycard_store_id,
            'ghl_connection_id' => $m->ghl_connection_id, 'action' => $action,
            'old_values' => $old, 'new_values' => $new, 'user_id' => $user?->id, 'created_at' => now(),
        ]);
        $this->logger->log('mapping.'.$action, [
            'outcome' => $action, 'store_id' => $m->store?->store_id, 'ghl_location_id' => $m->connection?->location_id,
            'message' => "Mapping {$action}".($user ? " by user #{$user->id}" : ''),
        ]);
    }
}
