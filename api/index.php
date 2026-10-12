<?php

// Forward Vercel Serverless Function requests to Laravel public/index.php

// 1. Prepare writable storage directories in /tmp for serverless environment
$storagePath = '/tmp/storage';

$dirs = [
    $storagePath . '/framework/views',
    $storagePath . '/framework/cache/data',
    $storagePath . '/framework/sessions',
    $storagePath . '/logs',
    $storagePath . '/bootstrap/cache',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// 2. Set environment variables for storage paths
putenv("LARAVEL_STORAGE_PATH={$storagePath}");
$_ENV['LARAVEL_STORAGE_PATH'] = $storagePath;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storagePath;

putenv("VIEW_COMPILED_PATH={$storagePath}/framework/views");
$_ENV['VIEW_COMPILED_PATH'] = "{$storagePath}/framework/views";
$_SERVER['VIEW_COMPILED_PATH'] = "{$storagePath}/framework/views";

putenv("APP_PACKAGES_CACHE={$storagePath}/bootstrap/cache/packages.php");
$_ENV['APP_PACKAGES_CACHE'] = "{$storagePath}/bootstrap/cache/packages.php";
$_SERVER['APP_PACKAGES_CACHE'] = "{$storagePath}/bootstrap/cache/packages.php";

putenv("APP_SERVICES_CACHE={$storagePath}/bootstrap/cache/services.php");
$_ENV['APP_SERVICES_CACHE'] = "{$storagePath}/bootstrap/cache/services.php";
$_SERVER['APP_SERVICES_CACHE'] = "{$storagePath}/bootstrap/cache/services.php";

// 3. Delegate to Laravel public entrypoint
require __DIR__ . '/../public/index.php';
