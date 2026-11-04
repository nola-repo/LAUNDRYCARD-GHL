<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Integration\IntegrationException;
use App\Http\Controllers\Controller;
use App\Models\GhlConnection;
use App\Services\BranchMappingService;
use App\Services\HighLevelClient;
use App\Services\HighLevelOAuthService;
use Illuminate\Http\Request;

/**
 * Handles GHL OAuth connection lifecycle and branch mapping triggers.
 */
class MappingController extends Controller
{
    /**
     * Redirect the admin browser to the GHL Marketplace install URL.
     * The OAuth callback handles persisting the connection AND
     * auto-mapping it to the best-matching LaundryCard store automatically.
     */
    public function connect(Request $request, HighLevelOAuthService $oauth)
    {
        try {
            return redirect()->away($oauth->authorizationUrl($request->user()->id));
        } catch (IntegrationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Verify the GHL API is still reachable for this connection. */
    public function testConnection(GhlConnection $connection, HighLevelClient $ghl)
    {
        try {
            $r = $ghl->testConnection($connection);

            return back()->with('result', [
                'title' => 'GHL connection test: OK',
                'data'  => ['location' => $r['location_name'], 'http_status' => $r['http_status']],
            ]);
        } catch (IntegrationException $e) {
            return back()->with('error', 'GHL connection test failed: '.$e->getMessage());
        }
    }

    /**
     * Locally mark a connection as disconnected.
     * The branch mapping is preserved so reinstalling resumes it automatically.
     */
    public function disconnect(Request $request, GhlConnection $connection, HighLevelOAuthService $oauth)
    {
        $request->validate(['confirm' => 'accepted']);
        $oauth->disconnect($connection);

        return back()->with('status', 'Connection disconnected locally. Reinstall the app to resume sync.');
    }

    /**
     * Trigger auto-mapping of all unmapped stores to their configured GHL locations.
     * Uses the explicit INTEGRATION_BRANCH_LOCATION_MAP config for deterministic wiring.
     */
    public function runAutoMap(BranchMappingService $mapper)
    {
        try {
            $mapped = $mapper->autoMapAll();

            $total = \App\Models\LaundrycardStore::where('status', 'active')->count();
            $verified = \App\Models\BranchMapping::where('status', \App\Models\BranchMapping::VERIFIED)->count();

            if ($mapped > 0) {
                return back()->with('status', "✅ Auto-mapping complete: {$mapped} new mapping(s) created. {$verified}/{$total} branches are now verified.");
            }

            if ($verified === $total && $total > 0) {
                return back()->with('status', "All {$total} branches are already mapped and verified. No changes needed.");
            }

            $unmapped = $total - $verified;
            return back()->with('error', "Auto-mapping could not resolve {$unmapped} branch(es). Check that the INTEGRATION_BRANCH_LOCATION_MAP config contains the correct store-to-location pairs and that both stores and OAuth connections are active.");
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Auto-mapping failed: ' . $e->getMessage());
        }
    }
}