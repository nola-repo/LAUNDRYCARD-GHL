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
use App\Services\LaundryCardClient;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(LaundryCardClient $lc)
    {
        $lastDiscovery = SyncRun::where('type', 'discovery')->whereIn('status', ['completed', 'partial'])->where('mode', 'live')->latest('finished_at')->first();
        $lastRun = SyncRun::where('type', 'discovery')->latest('id')->first();
        $lastLcApi = IntegrationEvent::where('stage', 'like', 'api.laundrycard')->latest('id')->first();

        $stats = [
            'branches' => LaundrycardStore::where('status', 'active')->count(),
            'locations' => GhlConnection::count(),
            'locations_active' => GhlConnection::where('status', GhlConnection::ACTIVE)->count(),
            'mappings_verified' => BranchMapping::where('status', BranchMapping::VERIFIED)->count(),
            'unmapped' => LaundrycardStore::where('status', 'active')->whereDoesntHave('activeMapping')->count(),
            'unassigned_locations' => GhlConnection::whereDoesntHave('activeMapping')->count(),
            'records' => Rec::count(),
            'new_customers' => Rec::whereIn('classification', [Rec::NEW_OBSERVED, Rec::CONFIRMED_NEW])->count(),
            'created' => Rec::where('processing_status', Rec::SYNCED_CREATED)->count(),
            'updated' => Rec::where('processing_status', Rec::SYNCED_UPDATED)->count(),
            'held' => Rec::where('processing_status', Rec::HELD)->count(),
            'skipped' => Rec::whereIn('processing_status', [Rec::SKIPPED])->count(),
            'baseline' => Rec::where('processing_status', Rec::BASELINE_SKIPPED)->count(),
            'failed' => Rec::where('processing_status', Rec::FAILED)->count(),
            'failed_attempts' => SyncAttempt::where('status', 'failed')->count(),
        ];

        return view('admin.overview', [
            'stats' => $stats,
            'lcConfigured' => $lc->isConfigured(),
            'syncEnabled' => (bool) config('integration.sync.enabled'),
            'lastRun' => $lastRun,
            'lastDiscovery' => $lastDiscovery,
            'lastLcApi' => $lastLcApi,
            'lastSync' => Rec::whereNotNull('synced_at')->max('synced_at'),
            'recentRuns' => SyncRun::latest('id')->limit(8)->get(),
            'recentEvents' => IntegrationEvent::latest('id')->limit(10)->get(),
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

        return view('admin.installations', ['connections' => $connections]);
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
        $q = Rec::with(['store', 'connection', 'contactMapping'])->latest('first_observed_at');

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
            Rec::NEW_OBSERVED => '✨ New Active Customers (Recent 90 Days)',
            Rec::BASELINE => '📁 Historical Baseline (Old accounts)',
            Rec::CONFIRMED_NEW => '⭐ Confirmed New Customers',
        ];

        $statusLabels = [
            Rec::PENDING => '⏳ Ready to Sync (Pending)',
            Rec::SYNCED_CREATED => '✅ Synced to GHL (New Contact)',
            Rec::SYNCED_UPDATED => '🔄 Synced to GHL (Updated Contact)',
            Rec::HELD => '⚠️ On Hold (Needs Attention / Excluded)',
            Rec::FAILED => '❌ Sync Failed',
            Rec::QUEUED => '📦 Queued for Sync',
            Rec::PROCESSING => '⚙️ Processing Sync',
            Rec::BASELINE_SKIPPED => '💤 Baseline (Historical Skipped)',
            Rec::SKIPPED => '⏭️ Skipped',
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

    /** Authenticated incremental feed for the monitoring dashboard. */
    public function updates(Request $request)
    {
        $after = max(0, (int) $request->query('after', 0));
        $events = IntegrationEvent::where('id', '>', $after)->orderBy('id')->limit(50)
            ->get(['id', 'created_at', 'stage', 'level', 'outcome', 'message', 'store_id', 'ghl_location_id']);
        $lastRun = SyncRun::where('type', 'discovery')->latest('id')->first();

        return response()->json([
            'updated_at' => now()->toIso8601String(),
            'poll_interval_seconds' => 4,
            'events' => $events,
            'last_event_id' => $events->last()?->id ?? $after,
            'connection_state' => 'connected',
            'stats' => [
                'branches' => LaundrycardStore::where('status', 'active')->count(),
                'locations' => GhlConnection::count(),
                'mappings_verified' => BranchMapping::where('status', BranchMapping::VERIFIED)->count(),
                'unmapped' => LaundrycardStore::where('status', 'active')->whereDoesntHave('activeMapping')->count(),
                'records' => Rec::count(),
                'new_customers' => Rec::whereIn('classification', [Rec::NEW_OBSERVED, Rec::CONFIRMED_NEW])->count(),
                'positive_balance' => Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)->count(),
                'zero_balance' => Rec::where('eligibility_status', Rec::BALANCE_ZERO)->count(),
                'balance_unknown' => Rec::whereIn('eligibility_status', [Rec::BALANCE_UNKNOWN, Rec::BALANCE_INVALID])->count(),
                'eligible_pending' => Rec::where('eligibility_status', Rec::BALANCE_ELIGIBLE)->whereIn('processing_status', [Rec::PENDING, Rec::QUEUED])->count(),
                'created' => Rec::where('processing_status', Rec::SYNCED_CREATED)->count(),
                'updated' => Rec::where('processing_status', Rec::SYNCED_UPDATED)->count(),
                'failed' => Rec::where('processing_status', Rec::FAILED)->count(),
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
