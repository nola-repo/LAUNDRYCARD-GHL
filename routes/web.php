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
Route::get('/oauth/ghl/callback', [OAuthController::class, 'callback'])->middleware('throttle:20,1')->name('oauth.callback');

// Vercel Cron trigger for customer discovery (guarded by CRON_SECRET)
Route::get('/api/cron/discovery', function (\Illuminate\Http\Request $request) {
    $secret = env('CRON_SECRET');
    if (!empty($secret)) {
        $auth = $request->header('Authorization');
        $queryToken = $request->query('token');
        if ($auth !== "Bearer {$secret}" && $queryToken !== $secret) {
            abort(403, 'Unauthorized cron execution');
        }
    }
    \Illuminate\Support\Facades\Artisan::call('integration:discover');
    return response()->json(['status' => 'ok', 'message' => 'Discovery triggered successfully']);
})->name('cron.discovery');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'overview'])->name('overview');
    Route::get('/installations', [DashboardController::class, 'installations'])->name('installations');
    Route::get('/mappings', [DashboardController::class, 'mappings'])->name('mappings');
    Route::get('/records', [DashboardController::class, 'records'])->name('records');
    Route::get('/logs', [DashboardController::class, 'logs'])->name('logs');
    Route::get('/failed', [DashboardController::class, 'failed'])->name('failed');
    Route::get('/developer', [DashboardController::class, 'developer'])->name('developer');

    Route::post('/installations/connect', [MappingController::class, 'connect'])->name('connect');
    Route::post('/installations/{connection}/test', [MappingController::class, 'testConnection'])->name('connection.test');
    Route::post('/installations/{connection}/disconnect', [MappingController::class, 'disconnect'])->name('connection.disconnect');

    Route::post('/mappings/assign', [MappingController::class, 'assign'])->name('mappings.assign');
    Route::post('/mappings/{mapping}/verify', [MappingController::class, 'verify'])->name('mappings.verify');
    Route::post('/mappings/{mapping}/disable', [MappingController::class, 'disable'])->name('mappings.disable');

    Route::post('/developer/test-laundrycard', [DeveloperController::class, 'testLaundryCard'])->name('dev.test-laundrycard');
    Route::post('/developer/refresh-stores', [DeveloperController::class, 'refreshStores'])->name('dev.refresh-stores');
    Route::post('/developer/scan', [DeveloperController::class, 'readOnlyScan'])->name('dev.scan');
    Route::post('/developer/routing-preview', [DeveloperController::class, 'routingPreview'])->name('dev.routing-preview');
    Route::post('/developer/process-test', [DeveloperController::class, 'processTestRecord'])->name('dev.process-test');
    Route::post('/records/{record}/retry', [DeveloperController::class, 'retry'])->name('records.retry');
});
