<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchMapping;
use App\Models\ContactMapping;
use App\Models\GhlConnection;
use App\Models\IntegrationEvent;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Models\MappingAuditLog;
use App\Models\SyncAttempt;
use App\Models\SyncRun;
use App\Services\BranchMappingService;
use App\Services\HighLevelOAuthService;
use App\Services\LaundryCardClient;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(LaundryCardClient $lc)
    {
        $lastDiscovery = SyncRun::where('type', 'discovery')->whereIn('status', ['completed', 'partial'])->where('mode', 'live')->latest('finished_at')->first();
        $lastRun = SyncRun::where('type', 'discovery')->latest('id')->first();
        $lastLcApi = IntegrationEvent::where('stage', 'like', 'api.laundrycard%')->latest('id')->first();
        $lastLcApiSuccess = IntegrationEvent::where('stage', 'like', 'api.laundrycard%')->where('http_status', '<', 400)->whereNotNull('http_status')->latest('id')->first();

        $cadence = (int) config('integration.discovery.schedule_every_minutes', 15);
        $nextScheduledRun = $lastRun?->started_at ? $lastRun->started_at->addMinutes($cadence) : null;
        $lastRunDuration = $lastRun && $lastRun->finished_at ? round($lastRun->started_at->diffInSeconds($lastRun->finished_at, true), 1) : null;

        $stats = [
            'branches' => LaundrycardStore::where('status', 'active')->count(),
            'locations' => GhlConnection::count(),
            'locations_active' => GhlConnection::where('status', GhlConnection::ACTIVE)->count(),
            'mappings_verified' => BranchMapping::where('status', BranchMapping::VERIFIED)->count(),
            'unmapped' => LaundrycardStore::where('status', 'active')->whereDoesntHave('activeMapping')->count(),
            'unassigned_locations' => GhlConnection::whereDoesntHave('activeMapping')->count(),
            'records' => Rec::count(),
            'new_customers' => Rec::whereIn('classification', [Rec::NEW_OBSERVED, Rec::CONFIRMED_NEW])->count(),
            'positive_balance' => Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)->count(),
            'zero_balance' => Rec::where('eligibility_status', Rec::BALANCE_ZERO)->count(),
            'balance_unknown' => Rec::whereIn('eligibility_status', [Rec::BALANCE_UNKNOWN, Rec::BALANCE_INVALID])->count(),
            'eligible_pending' => Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)->whereIn('processing_status', [Rec::PENDING, Rec::QUEUED])->count(),
            'created' => Rec::where('processing_status', Rec::SYNCED_CREATED)->count(),
            'updated' => Rec::where('processing_status', Rec::SYNCED_UPDATED)->count(),
            'held' => Rec::where('processing_status', Rec::HELD)->count(),
            'skipped' => Rec::whereIn('processing_status', [Rec::SKIPPED])->count(),
            'baseline' => Rec::where('processing_status', Rec::BASELINE_SKIPPED)->count(),
            'failed' => Rec::where('processing_status', Rec::FAILED)->count(),
            'failed_attempts' => SyncAttempt::where('status', 'failed')->count(),
        ];

        $stores = LaundrycardStore::with(['activeMapping.connection'])->withCount('sourceRecords')->orderBy('store_id')->get();

        return view('admin.overview', [
            'stats' => $stats,
            'stores' => $stores,
            'lcConfigured' => $lc->isConfigured(),
            'syncEnabled' => (bool) config('integration.sync.enabled'),
            'lastRun' => $lastRun,
            'lastDiscovery' => $lastDiscovery,
            'lastLcApi' => $lastLcApi,
            'lastLcApiSuccess' => $lastLcApiSuccess,
            'nextScheduledRun' => $nextScheduledRun,
            'lastRunDuration' => $lastRunDuration,
            'lastSync' => Rec::whereNotNull('synced_at')->max('synced_at'),
            'recentRuns' => SyncRun::latest('id')->limit(8)->get(),
            'recentEvents' => IntegrationEvent::latest('id')->limit(12)->get(),
            'latestNewCustomers' => Rec::with('store')
                ->whereIn('classification', [Rec::NEW_OBSERVED, Rec::CONFIRMED_NEW])
                ->latest('first_observed_at')->limit(10)->get(),
        ]);
    }

    public function installations()
    {
        $connections = GhlConnection::with('activeMapping.store')->orderBy('location_name')->get()->map(function ($c) {
            $c->recent_errors = IntegrationEvent::where('ghl_location_id', $c->location_id)
                ->where('stage', 'like', 'oauth.%')->whereIn('level', ['warning', 'error'])->latest('id')->limit(3)->get();

            return $c;
        });

        $recentOauthEvents = IntegrationEvent::where('stage', 'like', 'oauth.%')
            ->latest('id')->limit(15)->get();

        return view('admin.installations', [
            'connections' => $connections,
            'recentOauthEvents' => $recentOauthEvents,
        ]);
    }

    public function mappings(BranchMappingService $svc)
    {
        $stores = LaundrycardStore::with(['activeMapping.connection'])->orderBy('store_id')->get();
        $unassigned = $svc->unassignedConnections();

        $rows = $stores->map(function ($store) use ($svc) {
            return ['store' => $store, 'decision' => $svc->resolve($store)];
        });

        return view('admin.mappings', [
            'rows' => $rows,
            'unassigned' => $unassigned,
            'audit' => MappingAuditLog::with(['store', 'connection'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function records(Request $request)
    {
        $tab = $request->query('tab');
        $q = $this->buildRecordsQuery($request);

        $perPage = (int) $request->query('per_page', 15);
        if (! in_array($perPage, [15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $counts = [
            'all' => Rec::count(),
            'new' => Rec::where('classification', Rec::NEW_OBSERVED)->count(),
            'named' => Rec::whereNotNull('customer_name')->count(),
            'synced' => Rec::whereIn('processing_status', [Rec::SYNCED_CREATED, Rec::SYNCED_UPDATED])->count(),
            'held' => Rec::whereIn('processing_status', [Rec::HELD, Rec::FAILED])->count(),
        ];

        $classificationLabels = [
            Rec::NEW_OBSERVED => 'New Active Customers (Recent 90 Days)',
            Rec::BASELINE => 'Historical Baseline (Archived)',
            Rec::CONFIRMED_NEW => 'Confirmed New Customers',
        ];

        $statusLabels = [
            Rec::PENDING => 'Ready to Sync (Pending)',
            Rec::SYNCED_CREATED => 'Synced to GHL (New Contact)',
            Rec::SYNCED_UPDATED => 'Synced to GHL (Updated Contact)',
            Rec::HELD => 'On Hold (Needs Attention / Excluded)',
            Rec::FAILED => 'Sync Failed',
            Rec::QUEUED => 'Queued for Sync',
            Rec::PROCESSING => 'Processing Sync',
            Rec::BASELINE_SKIPPED => 'Baseline (Historical Skipped)',
            Rec::SKIPPED => 'Skipped',
        ];

        $holdReasonLabels = [
            'balance_not_positive' => '$0.00 Balance (Exempt until loaded)',
            'no_branch_mapping' => 'Store branch not mapped to GHL',
            'missing_contact_info' => 'Missing contact info (email/phone)',
            'circuit_breaker_tripped' => 'Sync paused for review',
        ];

        return view('admin.records', [
            'records' => $q->paginate($perPage)->withQueryString(),
            'stores' => LaundrycardStore::orderBy('store_id')->get(),
            'statuses' => [Rec::PENDING, Rec::SYNCED_CREATED, Rec::SYNCED_UPDATED, Rec::HELD, Rec::FAILED, Rec::QUEUED, Rec::PROCESSING, Rec::BASELINE_SKIPPED, Rec::SKIPPED],
            'holdReasons' => Rec::whereNotNull('hold_reason')->distinct()->pluck('hold_reason'),
            'counts' => $counts,
            'perPage' => $perPage,
            'activeTab' => $tab,
            'classificationLabels' => $classificationLabels,
            'statusLabels' => $statusLabels,
            'holdReasonLabels' => $holdReasonLabels,
        ]);
    }

    /** Stream export of filtered customer records to CSV. */
    public function exportRecords(Request $request)
    {
        $q = $this->buildRecordsQuery($request);
        $filename = 'customer-records-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($q) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($handle, [
                'Card Number',
                'Store ID',
                'Store Name',
                'First Name',
                'Last Name',
                'Customer Full Name',
                'Email',
                'Phone',
                'Current Balance',
                'Classification',
                'Eligibility Status',
                'Eligibility Reason',
                'Processing Status',
                'Hold Reason',
                'Reload Count',
                'Reload Total ($)',
                'GHL Location ID',
                'GHL Contact ID',
                'First Observed (UTC)',
                'Last Observed (UTC)',
                'Synced At (UTC)',
            ]);

            $q->chunk(500, function ($records) use ($handle) {
                foreach ($records as $r) {
                    [$first, $last] = \App\Support\Normalizer::splitName($r->customer_name);
                    fputcsv($handle, [
                        $r->card_number,
                        $r->store->store_id ?? '',
                        $r->store->name ?? '',
                        $first ?? '',
                        $last ?? '',
                        $r->customer_name ?? '',
                        $r->customer_email ?? '',
                        $r->customer_phone ?? '',
                        $r->balance !== null ? number_format((float) $r->balance, 2, '.', '') : '',
                        $r->classification ?? '',
                        $r->eligibility_status ?? '',
                        $r->eligibility_reason ?? '',
                        $r->processing_status ?? '',
                        $r->hold_reason ?? '',
                        $r->reload_count ?? 0,
                        $r->reload_total !== null ? number_format((float) $r->reload_total, 2, '.', '') : '0.00',
                        $r->destination_location_id ?? ($r->connection?->location_id ?? ''),
                        $r->contactMapping?->ghl_contact_id ?? '',
                        $r->first_observed_at?->toIso8601String() ?? '',
                        $r->last_observed_at?->toIso8601String() ?? '',
                        $r->synced_at?->toIso8601String() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function buildRecordsQuery(Request $request)
    {
        $tab = $request->query('tab');
        $q = Rec::with(['store', 'connection', 'contactMapping'])
            ->withCount(['transactions as reload_count' => fn ($tx) => $tx->where('transaction_type', 'reload')])
            ->withSum(['transactions as reload_total' => fn ($tx) => $tx->where('transaction_type', 'reload')], 'amount')
            ->latest('first_observed_at');

        // Quick search by card number
        if ($search = trim((string) $request->query('search', $request->query('q')))) {
            $q->where('card_number', 'like', "%{$search}%");
        }

        // Quick Tab Presets
        if ($tab === 'new') {
            $q->where('classification', Rec::NEW_OBSERVED);
        } elseif ($tab === 'named') {
            $q->whereNotNull('customer_name');
        } elseif ($tab === 'synced') {
            $q->whereIn('processing_status', [Rec::SYNCED_CREATED, Rec::SYNCED_UPDATED]);
        } elseif ($tab === 'held') {
            $q->whereIn('processing_status', [Rec::HELD, Rec::FAILED]);
        }

        if ($s = $request->query('store')) {
            $q->whereHas('store', fn ($x) => $x->where('store_id', $s));
        }
        if ($st = $request->query('status')) {
            $q->where('processing_status', $st);
        }
        if ($c = $request->query('classification')) {
            $q->where('classification', $c);
        }
        if ($hasName = $request->query('has_name')) {
            if ($hasName === 'yes') {
                $q->whereNotNull('customer_name');
            } elseif ($hasName === 'no') {
                $q->whereNull('customer_name');
            }
        }
        if ($o = $request->query('outcome')) {
            $q->where('hold_reason', $o);
        }
        if ($f = $request->query('from')) {
            $q->whereDate('first_observed_at', '>=', $f);
        }
        if ($t = $request->query('to')) {
            $q->whereDate('first_observed_at', '<=', $t);
        }

        return $q;
    }

    /** Credit history: reloads, bonuses and vends ingested from /api/transact. */
    public function transactions(Request $request)
    {
        $q = $this->buildTransactionsQuery($request);

        $card = trim((string) $request->query('card'));

        // Summary for the (filtered) set.
        $sumBase = clone $q;
        $sumBase->getQuery()->orders = null;
        $summary = [];
        foreach ($sumBase->selectRaw('transaction_type, count(*) as n, sum(amount) as total')->groupBy('transaction_type')->get() as $row) {
            $summary[$row->transaction_type ?? 'unknown'] = ['n' => (int) $row->n, 'total' => (float) $row->total];
        }

        $record = null;
        if ($card !== '' && ($rq = Rec::with('store')->where('card_number', $card))->count() >= 1) {
            $record = $rq->first();
        }

        return view('admin.transactions', [
            'transactions' => $q->paginate(50)->withQueryString(),
            'stores' => LaundrycardStore::orderBy('store_id')->get(),
            'summary' => $summary,
            'record' => $record,
            'totalStored' => \App\Models\LaundrycardTransaction::count(),
        ]);
    }

    /** Stream export of filtered transactions/credit history to CSV. */
    public function exportTransactions(Request $request)
    {
        $q = $this->buildTransactionsQuery($request);
        $filename = 'credit-history-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($q) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($handle, [
                'Transaction ID',
                'Occurred At (UTC)',
                'Store ID',
                'Store Name',
                'Card Number',
                'First Name',
                'Last Name',
                'Customer Full Name',
                'Transaction Type',
                'Raw Type Code',
                'Amount ($)',
                'Bonus Amount ($)',
                'Balance After ($)',
                'Machine Number',
                'Transaction Ref',
                'Additional Detail',
            ]);

            $q->chunk(500, function ($transactions) use ($handle) {
                foreach ($transactions as $t) {
                    $name = $t->sourceRecord?->customer_name;
                    [$first, $last] = \App\Support\Normalizer::splitName($name);
                    $info = is_array($t->raw_payload) ? ($t->raw_payload['AdditionalInfo'] ?? '') : '';

                    fputcsv($handle, [
                        $t->id,
                        $t->occurred_at?->toIso8601String() ?? '',
                        $t->store->store_id ?? '',
                        $t->store->name ?? '',
                        $t->card_number,
                        $first ?? '',
                        $last ?? '',
                        $name ?? '',
                        $t->transaction_type ?? 'unknown',
                        $t->transaction_type_raw ?? '',
                        $t->amount !== null ? number_format((float) $t->amount, 2, '.', '') : '',
                        $t->bonus_amount !== null ? number_format((float) $t->bonus_amount, 2, '.', '') : '',
                        $t->balance_after !== null ? number_format((float) $t->balance_after, 2, '.', '') : '',
                        $t->mach_no ?? '',
                        $t->transaction_ref ?? '',
                        (string) $info,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function buildTransactionsQuery(Request $request)
    {
        $q = \App\Models\LaundrycardTransaction::with(['store', 'sourceRecord'])->latest('occurred_at')->latest('id');

        $card = trim((string) $request->query('card'));
        if ($card !== '') {
            $q->where('card_number', 'like', "%{$card}%");
        }
        if ($s = $request->query('store')) {
            $q->whereHas('store', fn ($x) => $x->where('store_id', $s));
        }
        if ($t = $request->query('type')) {
            $t === 'unknown'
                ? $q->where(fn ($x) => $x->where('transaction_type', 'unknown')->orWhereNull('transaction_type'))
                : $q->where('transaction_type', $t);
        }
        if ($f = $request->query('from')) {
            $q->whereDate('occurred_at', '>=', $f);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('occurred_at', '<=', $to);
        }

        return $q;
    }

    public function logs(Request $request)
    {
        $q = IntegrationEvent::latest('id');
        if ($s = trim((string) $request->query('q'))) {
            $q->where(function ($x) use ($s) {
                $x->where('correlation_id', 'like', "%$s%")->orWhere('message', 'like', "%$s%")
                    ->orWhere('stage', 'like', "%$s%")->orWhere('store_id', 'like', "%$s%")->orWhere('ghl_location_id', 'like', "%$s%");
            });
        }
        foreach (['level', 'outcome'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($v = $request->query('stage')) {
            $q->where('stage', 'like', $v.'%');
        }

        return view('admin.logs', ['events' => $q->paginate(40)->withQueryString()]);
    }

    public function failed()
    {
        $records = Rec::with(['store', 'attempts' => fn ($q) => $q->latest('id')->limit(10)])
            ->whereIn('processing_status', [Rec::FAILED, Rec::HELD])->latest('updated_at')->paginate(25);

        return view('admin.failed', ['records' => $records]);
    }

    public function developer()
    {
        return view('admin.developer', [
            'diagnostics' => IntegrationEvent::where('stage', 'like', 'api.%')->latest('id')->limit(30)->get(),
            'runs' => SyncRun::latest('id')->limit(5)->get(),
        ]);
    }

    public function oauthDiagnose(HighLevelOAuthService $oauth)
    {
        $ghlConfig = config('integration.ghl');
        $isConfigured = $oauth->isConfigured();

        // Redact secrets for display
        $safeConfig = [
            'client_id' => filled($ghlConfig['client_id']) ? substr($ghlConfig['client_id'], 0, 8).'…' : '(missing)',
            'client_secret' => filled($ghlConfig['client_secret']) ? '••••••••' : '(missing)',
            'redirect_uri' => $ghlConfig['redirect_uri'] ?: '(missing)',
            'scopes' => $ghlConfig['scopes'],
            'authorize_url' => $ghlConfig['authorize_url'],
            'token_url' => $ghlConfig['token_url'],
            'api_base' => $ghlConfig['api_base'],
        ];

        $authUrlRedacted = null;
        $authUrlError = null;
        if ($isConfigured) {
            try {
                $url = $oauth->authorizationUrl();
                $authUrlRedacted = preg_replace('/([?&]state=)[^&]+/', '$1[REDACTED]', $url);
            } catch (\Throwable $e) {
                $authUrlError = $e->getMessage();
            }
        }

        $recentOauthEvents = IntegrationEvent::where('stage', 'like', 'oauth.%')
            ->latest('id')->limit(20)->get();

        return view('admin.oauth-diagnose', [
            'isConfigured' => $isConfigured,
            'safeConfig' => $safeConfig,
            'authUrlRedacted' => $authUrlRedacted,
            'authUrlError' => $authUrlError,
            'recentOauthEvents' => $recentOauthEvents,
        ]);
    }

    /** Authenticated incremental feed for the monitoring dashboard. */
    public function updates(Request $request)
    {
        $after = max(0, (int) $request->query('after', 0));
        $events = IntegrationEvent::where('id', '>', $after)->orderBy('id')->limit(50)
            ->get(['id', 'created_at', 'stage', 'level', 'outcome', 'message', 'store_id', 'ghl_location_id']);
        $lastRun = SyncRun::where('type', 'discovery')->latest('id')->first();
        $lastDiscovery = SyncRun::where('type', 'discovery')->whereIn('status', ['completed', 'partial'])->where('mode', 'live')->latest('finished_at')->first();
        $lastLcApi = IntegrationEvent::where('stage', 'like', 'api.laundrycard%')->latest('id')->first();
        $lastLcApiSuccess = IntegrationEvent::where('stage', 'like', 'api.laundrycard%')->where('http_status', '<', 400)->whereNotNull('http_status')->latest('id')->first();
        $cadence = (int) config('integration.discovery.schedule_every_minutes', 15);
        $nextScheduledRun = $lastRun?->started_at ? $lastRun->started_at->addMinutes($cadence) : null;
        $lastRunDuration = $lastRun && $lastRun->finished_at ? round($lastRun->started_at->diffInSeconds($lastRun->finished_at, true), 1) : null;

        return response()->json([
            'updated_at' => now()->toIso8601String(),
            'poll_interval_seconds' => 4,
            'events' => $events,
            'last_event_id' => $events->last()?->id ?? $after,
            'connection_state' => 'connected',
            'scheduler' => [
                'status' => $lastRun?->status ?? 'standby',
                'last_run_at' => $lastRun?->started_at?->toIso8601String(),
                'last_discovery_at' => $lastDiscovery?->finished_at?->toIso8601String(),
                'next_scheduled_at' => $nextScheduledRun?->toIso8601String(),
                'duration_seconds' => $lastRunDuration,
                'cadence_minutes' => $cadence,
                'last_error' => $lastRun?->error,
                'stats' => $lastRun?->stats,
            ],
            'api' => [
                'last_call_at' => $lastLcApi?->created_at?->toIso8601String(),
                'last_call_status' => $lastLcApi?->http_status,
                'last_success_at' => $lastLcApiSuccess?->created_at?->toIso8601String(),
            ],
            'stats' => [
                'branches' => LaundrycardStore::where('status', 'active')->count(),
                'locations' => GhlConnection::count(),
                'locations_active' => GhlConnection::where('status', GhlConnection::ACTIVE)->count(),
                'mappings_verified' => BranchMapping::where('status', BranchMapping::VERIFIED)->count(),
                'unmapped' => LaundrycardStore::where('status', 'active')->whereDoesntHave('activeMapping')->count(),
                'unassigned_locations' => GhlConnection::whereDoesntHave('activeMapping')->count(),
                'records' => Rec::count(),
                'new_customers' => Rec::whereIn('classification', [Rec::NEW_OBSERVED, Rec::CONFIRMED_NEW])->count(),
                'positive_balance' => Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)->count(),
                'zero_balance' => Rec::where('eligibility_status', Rec::BALANCE_ZERO)->count(),
                'balance_unknown' => Rec::whereIn('eligibility_status', [Rec::BALANCE_UNKNOWN, Rec::BALANCE_INVALID])->count(),
                'eligible_pending' => Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)->whereIn('processing_status', [Rec::PENDING, Rec::QUEUED])->count(),
                'created' => Rec::where('processing_status', Rec::SYNCED_CREATED)->count(),
                'updated' => Rec::where('processing_status', Rec::SYNCED_UPDATED)->count(),
                'held' => Rec::where('processing_status', Rec::HELD)->count(),
                'skipped' => Rec::whereIn('processing_status', [Rec::SKIPPED])->count(),
                'baseline' => Rec::where('processing_status', Rec::BASELINE_SKIPPED)->count(),
                'failed' => Rec::where('processing_status', Rec::FAILED)->count(),
                'failed_attempts' => SyncAttempt::where('status', 'failed')->count(),
                'last_discovery' => $lastRun?->finished_at?->toIso8601String(),
            ],
            'new_customers' => Rec::with('store')
                ->whereIn('classification', [Rec::NEW_OBSERVED, Rec::CONFIRMED_NEW])
                ->latest('first_observed_at')->limit(10)->get()
                ->map(fn (Rec $record) => [
                    'store_id' => $record->store?->store_id ?? '—',
                    'card_number' => substr($record->card_number, 0, 2).'••••'.substr($record->card_number, -2),
                    'customer_name' => $record->customer_name ? \Illuminate\Support\Str::limit($record->customer_name, 1, '').'***' : '—',
                    'customer_email' => \App\Support\Sanitizer::maskEmail($record->customer_email),
                    'customer_phone' => \App\Support\Sanitizer::maskPhone($record->customer_phone),
                    'balance' => $record->balance !== null ? '$'.number_format((float) $record->balance, 2) : 'unknown',
                    'eligibility' => $record->eligibility_status,
                    'classification' => $record->classification,
                    'first_observed_at' => $record->first_observed_at?->toIso8601String(),
                ])->values(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
