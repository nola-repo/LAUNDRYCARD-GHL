<?php

namespace App\Http\Controllers;

use App\Exceptions\Integration\IntegrationException;
use App\Services\HighLevelOAuthService;
use Illuminate\Http\Request;

/** Public redirect target for the GHL OAuth flow. Protected by the single-use state value. */
class OAuthController extends Controller
{
    public function callback(Request $request, HighLevelOAuthService $oauth)
    {
        try {
            $connection = $oauth->handleCallback($request->query());

            return response()->view('oauth.result', [
                'ok' => true,
                'message' => 'Location connected'.($connection->location_name ? ': '.$connection->location_name : '').'. An administrator must now assign a LaundryCard branch before synchronization is enabled.',
            ]);
        } catch (IntegrationException $e) {
            // Details are already in integration_events; keep the browser message generic.
            return response()->view('oauth.result', ['ok' => false, 'message' => 'The authorization could not be completed. Start the installation again from the admin dashboard.'], 400);
        }
    }
}
