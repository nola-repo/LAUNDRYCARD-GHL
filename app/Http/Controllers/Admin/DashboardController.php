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
            'lastRun' => $lastRun,
            'lastDiscovery' => $lastDiscovery,
            'lastLcApi' => $lastLcApi,
            'lastSync' => Rec::whereNotNull('synced_at')->max('synced_at'),
            'recentRuns' => SyncRun::latest('id')->limit(8)->get(),
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
        $stores = LaundrycardStore::with(['mappings' => fn ($q) => $q->whereIn('status', ['proposed', 'verified'])->with('connection')])
            ->orderBy('store_id')->get();
        $connections = GhlConnection::orderBy('location_name')->get();
        $unassigned = $svc->unassignedConnections();

        $rows = $stores->map(function ($s) use ($svc, $unassigned) {
            $verified = $s->mappings->firstWhere('status', 'verified');
            $proposed = $s->mappings->firstWhere('status', 'proposed');
            $suggestion = (! $verified && ! $proposed) ? $svc->suggest($s, $unassigned) : null;

            return ['store' => $s, 'verified' => $verified, 'proposed' => $proposed, 'suggestion' => $suggestion];
        });

        return view('admin.mappings', [
            'rows' => $rows,
            'connections' => $connections,
            'unassigned' => $unassigned,
            'audit' => MappingAuditLog::with(['user', 'store', 'connection'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function records(Request $request)
    {
        $q = Rec::with(['store', 'connection', 'contactMapping'])->latest('first_observed_at');
        if ($s = $request->query('store')) {
            $q->whereHas('store', fn ($x) => $x->where('store_id', $s));
        }
        if ($st = $request->query('status')) {
            $q->where('processing_status', $st);
        }
        if ($c = $request->query('classification')) {
            $q->where('classification', $c);
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

        return view('admin.records', [
            'records' => $q->paginate(25)->withQueryString(),
            'stores' => LaundrycardStore::orderBy('store_id')->get(),
            'statuses' => [Rec::BASELINE_SKIPPED, Rec::PENDING, Rec::QUEUED, Rec::PROCESSING, Rec::SYNCED_CREATED, Rec::SYNCED_UPDATED, Rec::HELD, Rec::SKIPPED, Rec::FAILED],
            'holdReasons' => Rec::whereNotNull('hold_reason')->distinct()->pluck('hold_reason'),
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
            'stores' => LaundrycardStore::orderBy('store_id')->get(),
            'testable' => Rec::with('store')->whereIn('processing_status', [Rec::BASELINE_SKIPPED, Rec::SKIPPED, Rec::HELD, Rec::PENDING, Rec::FAILED])->latest('id')->limit(100)->get(),
            'diagnostics' => IntegrationEvent::where('stage', 'like', 'api.%')->latest('id')->limit(30)->get(),
            'runs' => SyncRun::latest('id')->limit(5)->get(),
        ]);
    }
}
