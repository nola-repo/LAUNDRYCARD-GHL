<?php

use App\Http\Controllers\Admin\DashboardController;
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

// Vercel Cron trigger for customer discovery (guarded by CRON_SECRET)
Route::get('/api/cron/{task}', function (\Illuminate\Http\Request $request, string $task) {
    $secret = (string) config('integration.cron_secret');
    if ($secret === '' || ! hash_equals('Bearer '.$secret, (string) $request->header('Authorization'))) {
        abort(401, 'Unauthorized cron execution');
    }
    if ($task === 'discovery') {
        $exit = \Illuminate\Support\Facades\Artisan::call('integration:discover');
    } elseif ($task === 'queue') {
        // Vercel does not keep a Laravel worker alive between invocations. Process
        // at most one persisted job per bounded cron invocation.
        $exit = \Illuminate\Support\Facades\Artisan::call('queue:work', [
            'connection' => config('queue.default'), '--once' => true, '--tries' => 1, '--timeout' => 20,
        ]);
    } else {
        abort(404);
    }

    return response()->json(['status' => $exit === 0 ? 'completed' : 'failed', 'task' => $task], $exit === 0 ? 200 : 500);
})->whereIn('task', ['discovery', 'queue'])->name('cron.task');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'overview'])->name('overview');
    Route::get('/installations', [DashboardController::class, 'installations'])->name('installations');
    Route::get('/mappings', [DashboardController::class, 'mappings'])->name('mappings');
    Route::get('/records', [DashboardController::class, 'records'])->name('records');
    Route::get('/logs', [DashboardController::class, 'logs'])->name('logs');
    Route::get('/failed', [DashboardController::class, 'failed'])->name('failed');
    Route::get('/developer', [DashboardController::class, 'developer'])->name('developer');

    Route::get('/monitoring/updates', [DashboardController::class, 'updates'])->name('monitoring.updates');
});
