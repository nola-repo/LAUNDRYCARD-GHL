<?php

namespace App\Services;

use App\Models\BranchMapping;
use App\Models\GhlConnection;
use App\Models\LaundrycardStore;
use App\Models\MappingAuditLog;
use App\Support\Normalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Automated branch mapping from explicit business-owned store/location pairs.
 * Similar names and single-location assumptions are never routing evidence.
 */
class BranchMappingService
{
    public const NO_MAPPING          = 'no_mapping';
    public const NOT_VERIFIED        = 'mapping_not_verified';
    public const CONNECTION_UNUSABLE = 'connection_unusable';

    public function __construct(private IntegrationLogger $logger)
    {
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Discover stores through the API; register new ones; attempt auto-mapping.
     *
     * @return array{total:int,new:int,missing:int,auto_mapped:int}
     */
    public function refreshStores(LaundryCardClient $client): array
    {
        $stores = $client->stores();
        $seen   = [];
        $new    = 0;

        foreach ($stores as $s) {
            $seen[]  = $s['store_id'];
            $store   = LaundrycardStore::firstOrNew(['store_id' => $s['store_id']]);
            $isNew   = ! $store->exists;
            $store->fill(array_filter([
                'name'     => $s['name'],  'address' => $s['address'], 'city' => $s['city'],
                'state'    => $s['state'], 'zip_code' => $s['zip_code'], 'timezone' => $s['timezone'],
            ], fn ($v) => $v !== null));
            $store->live_enabled = $s['live_enabled'];
            $store->status       = 'active';
            $store->last_seen_at = now();
            $store->first_seen_at ??= now();
            $store->save();

            if ($isNew) {
                $new++;
                $this->logger->log('stores.discovered', [
                    'outcome'  => 'new',
                    'store_id' => $store->store_id,
                    'message'  => "New LaundryCard store {$store->store_id} registered; attempting auto-mapping.",
                ]);
            }
        }

        $missing = LaundrycardStore::where('status', 'active')->whereNotIn('store_id', $seen)->get();
        foreach ($missing as $m) {
            $m->update(['status' => 'missing']);
            $this->logger->log('stores.missing', [
                'level' => 'warning', 'store_id' => $m->store_id,
                'message' => 'Store no longer returned by API.',
            ]);
        }

        // Attempt to auto-map every still-unmapped store against all unassigned connections.
        $mapped = $this->autoMapAll();

        return ['total' => count($stores), 'new' => $new, 'missing' => $missing->count(), 'auto_mapped' => $mapped];
    }

    /** Re-evaluate configured associations after an OAuth install or reinstallation. */
    public function autoMapConnection(GhlConnection $connection): ?BranchMapping
    {
        $this->autoMapAll();

        return $connection->activeMapping()->first();
    }

    /**
     * Attempt auto-mapping for ALL unmapped stores against ALL unassigned connections.
     * Safe to call repeatedly — only creates mappings where none exist.
     *
     * @return int  Number of new auto-verified mappings created.
     */
    public function autoMapAll(): int
    {
        $mapped = 0;
        $configured = config('integration.branch_location_map', []);
        foreach ($configured as $storeId => $locationId) {
            $store = LaundrycardStore::where('store_id', Normalizer::storeId((string) $storeId))
                ->where('status', 'active')->first();
            $connection = GhlConnection::where('location_id', (string) $locationId)
                ->where('status', GhlConnection::ACTIVE)->first();
            if (! $store || ! $connection) {
                continue;
            }

            $current = $store->activeMapping;
            if ($current) {
                if ($current->ghl_connection_id !== $connection->id) {
                    $this->logger->log('mapping.auto', [
                        'level' => 'warning', 'outcome' => 'conflict', 'store_id' => $store->store_id,
                        'ghl_location_id' => $connection->location_id,
                        'message' => 'Configured destination conflicts with the existing active mapping; store remains unroutable.',
                    ]);
                }
                continue;
            }

            if ($connection->activeMapping()->exists()) {
                $this->logger->log('mapping.auto', [
                    'level' => 'warning', 'outcome' => 'ambiguous', 'store_id' => $store->store_id,
                    'ghl_location_id' => $connection->location_id,
                    'message' => 'Configured location is already assigned to another active store; no mapping was created.',
                ]);
                continue;
            }

            $this->createVerifiedMapping($store, $connection, null, 'explicit_business_mapping');
            $mapped++;
        }

        return $mapped;
    }

    /**
     * Routing decision for a store. The ONLY source of truth for destination.
     *
     * @return array{routable:bool,reason:?string,mapping:?BranchMapping,connection:?GhlConnection}
     */
    public function resolve(LaundrycardStore $store): array
    {
        $mapping = BranchMapping::with('connection')
            ->where('laundrycard_store_id', $store->id)
            ->where('status', BranchMapping::VERIFIED)
            ->first();

        if (! $mapping) {
            $hasProposal = BranchMapping::where('laundrycard_store_id', $store->id)
                ->where('status', BranchMapping::PROPOSED)
                ->exists();

            return [
                'routable'   => false,
                'reason'     => $hasProposal ? self::NOT_VERIFIED : self::NO_MAPPING,
                'mapping'    => null,
                'connection' => null,
            ];
        }

        $configuredLocationId = config('integration.branch_location_map.'.$store->store_id);
        if ($configuredLocationId && $mapping->connection?->location_id !== $configuredLocationId) {
            return [
                'routable' => false,
                'reason' => 'mapping_conflict',
                'mapping' => $mapping,
                'connection' => $mapping->connection,
            ];
        }

        $conn = $mapping->connection;
        if (! $conn || ! $conn->isUsable()) {
            return [
                'routable'   => false,
                'reason'     => self::CONNECTION_UNUSABLE,
                'mapping'    => $mapping,
                'connection' => $conn,
            ];
        }

        return ['routable' => true, 'reason' => null, 'mapping' => $mapping, 'connection' => $conn];
    }

    // ── Read-only helpers used by the monitoring dashboard ───────────────────

    public function unmappedStores()
    {
        return LaundrycardStore::where('status', 'active')
            ->whereDoesntHave('activeMapping')
            ->orderBy('store_id')
            ->get();
    }

    public function unassignedConnections()
    {
        return GhlConnection::whereDoesntHave('activeMapping')
            ->orderBy('location_name')
            ->get();
    }

    public function storeByAnyId(string|int $id): ?LaundrycardStore
    {
        return LaundrycardStore::where('store_id', Normalizer::storeId($id))->first();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Atomically create + immediately verify a branch mapping.
     * Idempotent: if the pair already exists it is just verified in-place.
     */
    private function createVerifiedMapping(
        LaundrycardStore $store,
        GhlConnection $connection,
        ?int $score,
        string $origin,
    ): BranchMapping {
        return DB::transaction(function () use ($store, $connection, $score, $origin) {
            // Re-check inside the transaction — another process may have raced us.
            if ($store->activeMapping()->exists() || $connection->activeMapping()->exists()) {
                return $store->activeMapping ?? $connection->activeMapping;
            }

            /** @var BranchMapping $mapping */
            $mapping = BranchMapping::firstOrCreate(
                ['laundrycard_store_id' => $store->id, 'ghl_connection_id' => $connection->id],
                ['status' => BranchMapping::PROPOSED, 'origin' => $origin, 'suggestion_score' => $score],
            );

            try {
                $mapping->update([
                    'status'                => BranchMapping::VERIFIED,
                    'verified_at'           => now(),
                    'verified_by'           => null,   // system-verified, not a human
                    'active_store_key'      => $store->id,
                    'active_connection_key' => $connection->id,
                    'suggestion_score'      => $score,
                    'origin'                => $origin,
                ]);
            } catch (QueryException) {
                // Unique constraint hit — another worker got there first.
                return $mapping->fresh();
            }

            MappingAuditLog::create([
                'branch_mapping_id'    => $mapping->id,
                'laundrycard_store_id' => $store->id,
                'ghl_connection_id'    => $connection->id,
                'action'               => 'auto_verified',
                'old_values'           => null,
                'new_values'           => ['status' => 'verified', 'origin' => $origin, 'score' => $score],
                'user_id'              => null,
                'created_at'           => now(),
            ]);

            $this->logger->log('mapping.auto', [
                'outcome'         => 'verified',
                'store_id'        => $store->store_id,
                'ghl_location_id' => $connection->location_id,
                'message'         => "Auto-verified: {$store->name} ↔ {$connection->location_name} (score={$score}, origin={$origin}).",
                'context'         => ['score' => $score, 'threshold' => config('integration.auto_map.threshold')],
            ]);

            return $mapping->fresh();
        });
    }
}
