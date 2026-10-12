<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Integration\IntegrationException;
use App\Http\Controllers\Controller;
use App\Models\BranchMapping;
use App\Models\GhlConnection;
use App\Models\LaundrycardStore;
use App\Services\BranchMappingService;
use App\Services\CustomerDiscoveryService;
use App\Services\HighLevelClient;
use App\Services\HighLevelOAuthService;
use DomainException;
use Illuminate\Http\Request;

class MappingController extends Controller
{
    public function assign(Request $request, BranchMappingService $svc)
    {
        $data = $request->validate([
            'laundrycard_store_id' => 'required|exists:laundrycard_stores,id',
            'ghl_connection_id' => 'required|exists:ghl_connections,id',
            'confirm' => 'accepted',
        ], ['confirm.accepted' => 'Tick the confirmation box: this changes where future customers are sent.']);

        $store = LaundrycardStore::findOrFail($data['laundrycard_store_id']);
        $conn = GhlConnection::findOrFail($data['ghl_connection_id']);
        $score = $request->integer('score') ?: null;

        try {
            $svc->assign($store, $conn, $request->user(), $score ? 'suggested' : 'manual', $score);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Proposed mapping {$store->store_id} → {$conn->location_name}. Verify it to enable synchronization.");
    }

    public function verify(Request $request, BranchMapping $mapping, BranchMappingService $svc, CustomerDiscoveryService $discovery)
    {
        $request->validate(['confirm' => 'accepted'], ['confirm.accepted' => 'Confirm that the store and location are the same business.']);
        try {
            $svc->verify($mapping, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        // Release records that were parked only because routing was impossible.
        $queued = $discovery->dispatchPending($mapping->store);

        return back()->with('status', "Mapping verified. {$queued} held record(s) queued.");
    }

    public function disable(Request $request, BranchMapping $mapping, BranchMappingService $svc)
    {
        $request->validate(['confirm' => 'accepted'], ['confirm.accepted' => 'Confirm disabling synchronization for this branch.']);
        $svc->disable($mapping, $request->user(), 'manual');

        return back()->with('status', 'Mapping disabled. History is retained; no new records will be sent.');
    }

    // ---- connection actions (installations page)

    public function connect(Request $request, HighLevelOAuthService $oauth)
    {
        try {
            return redirect()->away($oauth->authorizationUrl($request->user()->id));
        } catch (IntegrationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function testConnection(GhlConnection $connection, HighLevelClient $ghl)
    {
        try {
            $r = $ghl->testConnection($connection);

            return back()->with('result', ['title' => 'GHL connection test: OK', 'data' => ['location' => $r['location_name'], 'http_status' => $r['http_status']]]);
        } catch (IntegrationException $e) {
            return back()->with('error', 'GHL connection test failed: '.$e->getMessage());
        }
    }

    public function disconnect(Request $request, GhlConnection $connection, HighLevelOAuthService $oauth)
    {
        $request->validate(['confirm' => 'accepted']);
        $oauth->disconnect($connection);

        return back()->with('status', 'Connection disconnected locally. Mappings are kept; reinstall the app to resume.');
    }
}
