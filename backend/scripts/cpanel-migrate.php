<?php

/**
 * One-time cPanel migrate (no SSH).
 *
 * 1. Copy this file into the API public/ folder (document root of usher.esmusstrading.com).
 * 2. Open:
 *    https://usher.esmusstrading.com/cpanel-migrate.php?key=VALIDITY_CPANEL_MIGRATE_8f3c
 * 3. Delete this file from public/ as soon as it finishes.
 */

declare(strict_types=1);

const MIGRATE_KEY = 'VALIDITY_CPANEL_MIGRATE_8f3c';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function migrate_page(int $status, string $title, string $body): never
{
    http_response_code($status);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'
        .htmlspecialchars($title, ENT_QUOTES)
        .'</title><style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.45}pre{white-space:pre-wrap;background:#111;color:#eee;padding:16px;border-radius:8px}strong{color:#b45309}</style></head><body>';
    echo '<h1>'.htmlspecialchars($title, ENT_QUOTES).'</h1>';
    echo $body;
    echo '</body></html>';
    exit;
}

$key = (string) ($_GET['key'] ?? '');
if (! hash_equals(MIGRATE_KEY, $key)) {
    migrate_page(404, 'Not found', '<p>Missing or wrong key.</p>');
}

if (PHP_VERSION_ID < 80401) {
    migrate_page(500, 'PHP too old', '<p>This app needs PHP 8.4.1+. In cPanel MultiPHP Manager set <strong>usher.esmusstrading.com</strong> to PHP 8.4, then retry.</p><p>This request used PHP '.htmlspecialchars(PHP_VERSION, ENT_QUOTES).'.</p>');
}

$root = dirname(__DIR__);
if (! is_file($root.'/artisan')) {
    migrate_page(500, 'Laravel not found', '<p>Could not find artisan. Put this file in the API <code>public/</code> folder (the document root of usher.esmusstrading.com).</p>');
}

$envFile = $root.'/.env';
if (! is_file($envFile)) {
    migrate_page(500, '.env missing', '<p>Keep the live <code>.env</code> on the server. Do not upload a new one from the zip.</p>');
}

$env = file_get_contents($envFile) ?: '';
$dbConnection = 'sqlite';
$dbPath = $root.'/database/database.sqlite';
if (preg_match('/^DB_CONNECTION=(.*)$/m', $env, $match)) {
    $dbConnection = trim($match[1], " \t\"'");
}
if (preg_match('/^DB_DATABASE=(.*)$/m', $env, $match)) {
    $configured = trim($match[1], " \t\"'");
    if ($configured !== '') {
        $dbPath = $configured;
    }
}

if ($dbConnection === 'sqlite') {
    if (! is_file($dbPath)) {
        migrate_page(500, 'Database file missing', '<p>SQLite file not found at <code>'.htmlspecialchars($dbPath, ENT_QUOTES).'</code>. The zip does not include the database. Leave the live file in place.</p>');
    }
    if (! is_writable($dbPath) || ! is_writable(dirname($dbPath))) {
        migrate_page(500, 'Database not writable', '<p>PHP cannot write <code>'.htmlspecialchars($dbPath, ENT_QUOTES).'</code>. Fix File Manager permissions, then retry.</p>');
    }
}

foreach ([
    $root.'/storage/app/private',
    $root.'/storage/app/public',
    $root.'/storage/framework/cache/data',
    $root.'/storage/framework/sessions',
    $root.'/storage/framework/views',
    $root.'/storage/logs',
    $root.'/bootstrap/cache',
] as $dir) {
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        migrate_page(500, 'Storage not writable', '<p>Could not create <code>'.htmlspecialchars($dir, ENT_QUOTES).'</code>.</p>');
    }
}

require $root.'/vendor/autoload.php';
/** @var \Illuminate\Foundation\Application $app */
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

ob_start();
$migrate = $kernel->call('migrate', ['--force' => true]);
$migrateOutput = $kernel->output();
$kernel->call('config:clear');
$kernel->call('route:clear');
$kernel->call('view:clear');
$kernel->call('config:cache');
$kernel->call('route:cache');
$kernel->call('view:cache');
$extra = ob_get_clean() ?: '';

$body = '<p>Using PHP '.htmlspecialchars(PHP_VERSION, ENT_QUOTES).'</p>';
$body .= '<p>Database: <code>'.htmlspecialchars($dbPath, ENT_QUOTES).'</code></p>';
$body .= '<h2>migrate --force</h2><pre>'.htmlspecialchars(trim($migrateOutput."\n".$extra), ENT_QUOTES).'</pre>';
if ($migrate !== 0) {
    $body .= '<p><strong>Migrate failed.</strong> Do not delete this file until it succeeds.</p>';
    migrate_page(500, 'Migrate failed', $body);
}

$body .= '<p><strong>Delete <code>public/cpanel-migrate.php</code> in File Manager now.</strong> Anyone with the URL can run it until you remove it.</p>';
$body .= '<p>No seeders were run. Check <a href="/up">/up</a>.</p>';
migrate_page(200, 'Migrations finished', $body);
