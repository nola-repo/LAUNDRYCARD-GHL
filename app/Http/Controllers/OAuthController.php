<?php

namespace App\Http\Controllers;

use App\Exceptions\Integration\AuthorizationException;
use App\Exceptions\Integration\IntegrationException;
use App\Exceptions\Integration\MalformedResponseException;
use App\Exceptions\Integration\RetryableException;
use App\Services\HighLevelOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Public redirect target for the GHL OAuth flow. Protected by the single-use state value. */
class OAuthController extends Controller
{
    public function callback(Request $request, HighLevelOAuthService $oauth)
    {
        try {
            $connection = $oauth->handleCallback($request->query());
            $bulkCount = (int) ($connection->getAttribute('agency_bulk_install_count') ?? 0);
            $message = $bulkCount > 0
                ? $bulkCount.' configured GoHighLevel branch location'.($bulkCount === 1 ? '' : 's').' connected.'
                : 'Location connected'.($connection->location_name ? ': '.$connection->location_name : '').'.';

            return response()->view('oauth.result', [
                'ok' => true,
                'message' => $message.' An administrator must now assign a LaundryCard branch before synchronization is enabled.',
            ]);

        } catch (AuthorizationException $e) {
            Log::channel('integration')->error('[oauth.callback] AuthorizationException: '.$e->getMessage(), [
                'exception' => get_class($e),
                'stage' => $e->stage ?? null,
                'http_status' => $e->httpStatus ?? null,
            ]);
            $hint = 'Authorization was denied or the session expired. Please start the installation again.';
            return $this->failureResponse($request, $hint, $e);

        } catch (MalformedResponseException $e) {
            Log::channel('integration')->error('[oauth.callback] MalformedResponseException: '.$e->getMessage(), [
                'exception' => get_class($e),
                'stage' => $e->stage ?? null,
            ]);
            $hint = 'GoHighLevel returned an unexpected response during token exchange. Check the API Activity log.';
            return $this->failureResponse($request, $hint, $e);

        } catch (RetryableException $e) {
            Log::channel('integration')->error('[oauth.callback] RetryableException: '.$e->getMessage(), [
                'exception' => get_class($e),
                'stage' => $e->stage ?? null,
            ]);
            $hint = 'The GoHighLevel service was temporarily unavailable. Please try again in a moment.';
            return $this->failureResponse($request, $hint, $e);

        } catch (IntegrationException $e) {
            Log::channel('integration')->error('[oauth.callback] IntegrationException: '.$e->getMessage(), [
                'exception' => get_class($e),
            ]);
            $hint = 'The authorization could not be completed. Start the installation again from the admin dashboard.';
            return $this->failureResponse($request, $hint, $e);

        } catch (\Throwable $e) {
            Log::channel('integration')->critical('[oauth.callback] Unexpected exception: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $hint = 'An unexpected error occurred. Please contact the system administrator.';
            return $this->failureResponse($request, $hint, $e);
        }
    }

    /** Build the auth URL without redirecting so admins can inspect and copy it. */
    public function authUrl(HighLevelOAuthService $oauth)
    {
        try {
            $url = $oauth->authorizationUrl();
            // Redact the state parameter before returning it to the UI.
            $redacted = preg_replace('/([?&]state=)[^&]+/', '$1[REDACTED]', $url);
            return response()->json(['url' => $url, 'redacted' => $redacted]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function failureResponse(Request $request, string $hint, \Throwable $e)
    {
        $debugMode = app()->environment('local', 'development') && $request->query('debug') === '1';

        $viewData = [
            'ok' => false,
            'message' => $hint,
            'timestamp' => now()->toDateTimeString(),
        ];

        if ($debugMode) {
            $viewData['debug'] = [
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'stage' => method_exists($e, 'stage') ? $e->stage : null,
            ];
        }

        return response()->view('oauth.result', $viewData, 400);
    }
}
