<?php

namespace App\Services;

use App\Models\BranchMapping;
use App\Models\GhlConnection;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Support\Normalizer;
use Illuminate\Support\Collection;

/**
 * Pre-flight validation and dry-run preview for contact synchronization.
 *
 * Production sync remains disabled until all prerequisites pass.
 * This service provides the information required for administrative approval
 * before INTEGRATION_CONTACT_SYNC_ENABLED is set to true.
 */
class SyncPreviewService
{
    public function __construct(
        private BranchMappingService $mappings,
        private ContactClassificationService $classifier,
        private HighLevelClient $ghl,
    ) {}

    /**
     * Full pre-flight check — returns structured results for admin review.
     *
     * @return array{
     *   sync_enabled: bool,
     *   prerequisites: array,
     *   eligible_count: int,
     *   identity_gaps: array,
     *   branch_routing: array,
     *   custom_field_config: array,
     *   sample_records: array,
     * }
     */
    public function preflightCheck(): array
    {
        $prerequisites = $this->checkPrerequisites();
        $eligibleQuery = Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)
            ->whereNotNull('balance')
            ->where('balance', '>', 0);

        $eligibleCount = (clone $eligibleQuery)->count();
        $identityGaps  = $this->computeIdentityGaps($eligibleQuery, $eligibleCount);
        $branchRouting = $this->branchRoutingCheck();
        $customFields  = $this->customFieldCheck();
        $sample        = $this->sampleRecords((clone $eligibleQuery)->with('store')->limit(10)->get());

        return [
            'sync_enabled'           => (bool) config('integration.sync.enabled'),
            'prerequisites'          => $prerequisites,
            'all_prerequisites_pass' => collect($prerequisites)->every(fn ($p) => $p['pass']),
            'eligible_count'         => $eligibleCount,
            'identity_gaps'          => $identityGaps,
            'branch_routing'         => $branchRouting,
            'custom_field_config'    => $customFields,
            'sample_records'         => $sample,
        ];
    }

    /**
     * Run a small controlled test batch in dry-run mode.
     * Returns what would happen without making any GHL API calls.
     */
    public function dryRun(int $limit = 5): array
    {
        $records = Rec::with('store')
            ->where('eligibility_status', Rec::BALANCE_ELIGIBLE)
            ->where('processing_status', Rec::PENDING)
            ->whereNotNull('balance')
            ->where('balance', '>', 0)
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($records as $record) {
            $email = Normalizer::email($record->customer_email);
            $phone = Normalizer::phone($record->customer_phone);
            $decision = $this->mappings->resolve($record->store);
            $classification = $this->classifier->classify(
                $record,
                [],
                $decision['connection']?->location_id ?? '',
            );

            $results[] = [
                'card_number'    => $record->card_number,
                'store_id'       => $record->store?->store_id,
                'has_email'      => filled($email),
                'has_phone'      => filled($phone),
                'has_name'       => filled($record->customer_name),
                'balance'        => $record->balance,
                'routable'       => $decision['routable'],
                'route_reason'   => $decision['reason'],
                'location_id'    => $decision['connection']?->location_id,
                'would_action'   => $this->predictAction($record, $decision, $email, $phone),
                'predicted_tags' => $classification['managed_tags'],
                'custom_fields'  => $classification['custom_fields'],
            ];
        }

        return [
            'mode'    => 'dry_run',
            'limit'   => $limit,
            'count'   => count($results),
            'records' => $results,
        ];
    }

    // ------------------------------------------------------------------ prerequisites

    private function checkPrerequisites(): array
    {
        $lc = app(LaundryCardClient::class);
        $diag = $lc->configDiagnostics();

        $checks = [];

        $checks['laundrycard_credentials'] = [
            'label' => 'LaundryCard API credentials',
            'pass'  => $diag['configured'],
            'detail' => $diag['configured'] ? 'Username and password are set.' : 'LAUNDRYCARD_USERNAME or LAUNDRYCARD_PASSWORD missing.',
        ];

        $checks['ghl_credentials'] = [
            'label' => 'GHL OAuth credentials',
            'pass'  => filled(config('integration.ghl.client_id')) && filled(config('integration.ghl.client_secret')),
            'detail' => 'GHL_CLIENT_ID and GHL_CLIENT_SECRET must be set.',
        ];

        $activeConnections = GhlConnection::where('status', GhlConnection::ACTIVE)->count();
        $checks['ghl_connections'] = [
            'label' => 'GHL active connections',
            'pass'  => $activeConnections > 0,
            'detail' => "{$activeConnections} active GHL connection(s).",
        ];

        $verifiedMappings = BranchMapping::where('status', BranchMapping::VERIFIED)->count();
        $checks['branch_mappings'] = [
            'label' => 'Branch mappings verified',
            'pass'  => $verifiedMappings > 0,
            'detail' => "{$verifiedMappings} verified branch mapping(s).",
        ];

        $eligibleCount = Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)
            ->whereIn('processing_status', [Rec::PENDING, Rec::HELD])
            ->count();
        $checks['eligible_records'] = [
            'label' => 'Eligible records ready',
            'pass'  => $eligibleCount > 0,
            'detail' => "{$eligibleCount} record(s) eligible for sync.",
        ];

        $checks['sync_engine'] = [
            'label' => 'Contact Sync Engine status',
            'pass'  => true,
            'detail' => config('integration.sync.enabled') ? '✅ Live Sync Engine is ENABLED.' : '⚠️  Sync is off (Safety Hold).',
        ];

        return $checks;
    }

    // ------------------------------------------------------------------ analysis helpers

    private function computeIdentityGaps($query, int $total): array
    {
        $noEmail = (clone $query)->whereNull('customer_email')->count();
        $noPhone = (clone $query)->whereNull('customer_phone')->count();
        $noName  = (clone $query)->whereNull('customer_name')->count();
        $noAll   = (clone $query)->whereNull('customer_email')->whereNull('customer_phone')->whereNull('customer_name')->count();

        return [
            'no_email'      => $noEmail,
            'no_phone'      => $noPhone,
            'no_name'       => $noName,
            'no_identifier' => $noAll,
            'sendable'      => max(0, $total - $noAll),
        ];
    }

    private function branchRoutingCheck(): array
    {
        $stores = LaundrycardStore::where('status', 'active')->with('activeMapping.connection')->get();
        $results = [];
        foreach ($stores as $store) {
            $decision = $this->mappings->resolve($store);
            $results[$store->store_id] = [
                'routable'      => $decision['routable'],
                'reason'        => $decision['reason'],
                'location_id'   => $decision['connection']?->location_id,
                'location_name' => $decision['connection']?->location_name,
                'connection_ok' => $decision['connection']?->isUsable() ?? false,
            ];
        }

        return $results;
    }

    private function customFieldCheck(): array
    {
        $configured = config('integration.classification.custom_fields', []);
        $connections = GhlConnection::where('status', GhlConnection::ACTIVE)->get();
        $results = [];

        foreach ($connections as $conn) {
            $fields = $configured[$conn->location_id] ?? [];
            $results[$conn->location_id] = [
                'location_name'   => $conn->location_name,
                'card_number'     => ! empty($fields['card_number']),
                'balance'         => ! empty($fields['balance']),
                'store_id'        => ! empty($fields['store_id']),
                'last_reload_date' => ! empty($fields['last_reload_date']),
                'any_configured'  => ! empty($fields),
            ];
        }

        return $results;
    }

    private function sampleRecords(Collection $records): array
    {
        return $records->map(function (Rec $record) {
            $email = Normalizer::email($record->customer_email);
            $phone = Normalizer::phone($record->customer_phone);
            $classification = $this->classifier->classify($record);

            return [
                'store_id'    => $record->store?->store_id,
                'has_email'   => filled($email),
                'has_phone'   => filled($phone),
                'has_name'    => filled($record->customer_name),
                'balance'     => $record->balance,
                'eligibility' => $record->eligibility_status,
                'status'      => $record->processing_status,
                'predicted_tags' => $classification['managed_tags'],
            ];
        })->toArray();
    }

    private function predictAction(Rec $record, array $decision, ?string $email, ?string $phone): string
    {
        if (! $decision['routable']) {
            return 'hold:' . ($decision['reason'] ?? 'not_routable');
        }
        [$first] = Normalizer::splitName($record->customer_name);
        if (! filled($email) && ! filled($phone) && ! filled($first)) {
            return 'skip:no_identifier';
        }

        return 'create_or_update';
    }
}

