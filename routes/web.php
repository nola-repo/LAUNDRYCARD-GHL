<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeveloperController;
use App\Http\Controllers\Admin\MappingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Provider redirect target. Guarded by single-use OAuth state, not by session.
Route::get('/oauth/callback', [OAuthController::class, 'callback'])->middleware('throttle:20,1')->name('oauth.callback');
Route::get('/oauth/ghl/callback', [OAuthController::class, 'callback'])->middleware('throttle:20,1');
// Returns the auth URL (state redacted) for admin inspection — no redirect.
Route::get('/oauth/auth-url', [OAuthController::class, 'authUrl'])->middleware(['auth', 'admin'])->name('oauth.auth-url');

// Vercel Cron & External Webhook trigger for customer discovery
$cronHandler = function (\Illuminate\Http\Request $request, string $task) {
    $secret = (string) config('integration.cron_secret');
    $authHeader = (string) $request->header('Authorization');
    $querySecret = (string) $request->query('secret');

    $authorized = $secret !== '' && (
        hash_equals('Bearer '.$secret, $authHeader) ||
        hash_equals($secret, $querySecret)
    );

    if (! $authorized) {
        abort(401, 'Unauthorized cron execution');
    }

    $startedAt = microtime(true);

    if ($task === 'discovery') {
        $exit = \Illuminate\Support\Facades\Artisan::call('integration:discover');
        $run = \App\Models\SyncRun::where('type', 'discovery')->latest('id')->first();
        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        return response()->json([
            'status'     => $exit === 0 ? 'completed' : 'failed',
            'task'       => $task,
            'duration_ms' => $duration,
            'run_id'     => $run?->id,
            'run_status' => $run?->status,
            'stats'      => $run?->stats,
            'error'      => $run?->error,
        ], $exit === 0 ? 200 : 500);
    } elseif ($task === 'queue') {
        // Vercel serverless: process a small bounded batch of queued jobs.
        // Each invocation handles up to 5 jobs to stay within the 30s maxDuration.
        $processed = 0;
        $failed    = 0;
        for ($i = 0; $i < 5; $i++) {
            $exit = \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => config('queue.default'),
                '--once'    => true,
                '--tries'   => 1,
                '--timeout' => 20,
                '--stop-when-empty' => true,
            ]);
            if ($exit === 0) {
                $processed++;
            } elseif ($exit === 1) {
                // Queue was empty — stop early.
                break;
            } else {
                $failed++;
            }
            // Check remaining time budget (Vercel maxDuration=30s).
            if ((microtime(true) - $startedAt) > 24) {
                break;
            }
        }
        $backlog = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        return response()->json([
            'status'      => 'completed',
            'task'        => $task,
            'processed'   => $processed,
            'failed'      => $failed,
            'backlog'     => $backlog,
            'duration_ms' => $duration,
        ]);
    } elseif ($task === 'transact') {
        // Transaction ingestion — only runs if enabled via config.
        if (! config('integration.transactions.enabled')) {
            return response()->json(['status' => 'skipped', 'reason' => 'transaction_ingestion_disabled'], 200);
        }
        $exit = \Illuminate\Support\Facades\Artisan::call('integration:ingest-transactions');
        $run  = \App\Models\SyncRun::where('type', 'transaction_ingest')->latest('id')->first();
        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        return response()->json([
            'status'     => $exit === 0 ? 'completed' : 'failed',
            'task'       => $task,
            'duration_ms' => $duration,
            'run_id'     => $run?->id,
            'run_status' => $run?->status,
            'stats'      => $run?->stats,
        ], $exit === 0 ? 200 : 500);
    } else {
        abort(404);
    }
};

Route::get('/api/cron/{task}', $cronHandler)->whereIn('task', ['discovery', 'queue', 'transact'])->name('cron.task');
Route::get('/cron/{task}', $cronHandler)->whereIn('task', ['discovery', 'queue', 'transact']);

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'overview'])->name('overview');
    Route::get('/installations', [DashboardController::class, 'installations'])->name('installations');
    Route::get('/mappings', [DashboardController::class, 'mappings'])->name('mappings');
    Route::get('/records', [DashboardController::class, 'records'])->name('records');
    Route::get('/transactions', [DashboardController::class, 'transactions'])->name('transactions');
    Route::get('/logs', [DashboardController::class, 'logs'])->name('logs');
    Route::get('/failed', [DashboardController::class, 'failed'])->name('failed');
    Route::get('/developer', [DashboardController::class, 'developer'])->name('developer');
    Route::get('/oauth/diagnose', [DashboardController::class, 'oauthDiagnose'])->name('oauth.diagnose');

    Route::get('/connect', [MappingController::class, 'connect'])->name('connect');
    Route::post('/connections/{connection}/test', [MappingController::class, 'testConnection'])->name('connections.test');
    Route::post('/connections/{connection}/disconnect', [MappingController::class, 'disconnect'])->name('connections.disconnect');
    Route::post('/mappings/auto-map', [MappingController::class, 'runAutoMap'])->name('mappings.auto-map');

    Route::get('/monitoring/updates', [DashboardController::class, 'updates'])->name('monitoring.updates');

    // Developer tools — Phase 1–8 additions
    Route::post('/developer/sync-preview', [DeveloperController::class, 'syncPreview'])->name('developer.sync-preview');
    Route::post('/developer/sync-dry-run', [DeveloperController::class, 'syncDryRun'])->name('developer.sync-dry-run');
    Route::post('/developer/config-diagnostics', [DeveloperController::class, 'configDiagnostics'])->name('developer.config-diagnostics');
    Route::post('/developer/tag-preview', [DeveloperController::class, 'tagPreview'])->name('developer.tag-preview');
    Route::post('/developer/enrich-preview', [DeveloperController::class, 'enrichPreview'])->name('developer.enrich-preview');
});
