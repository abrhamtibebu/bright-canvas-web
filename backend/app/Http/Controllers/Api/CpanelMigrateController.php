<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class CpanelMigrateController extends Controller
{
    public const KEY = 'VALIDITY_CPANEL_MIGRATE_8f3c';

    public function __invoke(Request $request): Response
    {
        if (! hash_equals(self::KEY, (string) $request->query('key'))) {
            abort(404);
        }

        if (PHP_VERSION_ID < 80401) {
            return $this->page(500, 'PHP too old', '<p>This app needs PHP 8.4.1+. In cPanel MultiPHP Manager set <strong>usher.esmusstrading.com</strong> to PHP 8.4, then retry.</p><p>This request used PHP '.e(PHP_VERSION).'.</p>');
        }

        $connection = (string) config('database.default');
        $database = (string) config('database.connections.'.$connection.'.database');
        if ($connection === 'sqlite') {
            if (! is_file($database)) {
                return $this->page(500, 'Database file missing', '<p>SQLite file not found at <code>'.e($database).'</code>. Leave the live database on the server.</p>');
            }
            if (! is_writable($database) || ! is_writable(dirname($database))) {
                return $this->page(500, 'Database not writable', '<p>PHP cannot write <code>'.e($database).'</code>.</p>');
            }
        }

        $code = Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        $body = '<p>Using PHP '.e(PHP_VERSION).'</p>';
        $body .= '<p>Database: <code>'.e($database).'</code></p>';
        $body .= '<h2>migrate --force</h2><pre>'.e($output).'</pre>';
        if ($code !== 0) {
            return $this->page(500, 'Migrate failed', $body.'<p><strong>Fix the error and open the same URL again.</strong></p>');
        }

        return $this->page(200, 'Migrations finished', $body.'<p>No seeders were run. Check <a href="/up">/up</a>.</p><p>You can leave this URL unused after it succeeds. Set <code>APP_DEBUG=false</code> in <code>.env</code> when you are done.</p>');
    }

    private function page(int $status, string $title, string $body): Response
    {
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'.e($title).'</title>'
            .'<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.45}pre{white-space:pre-wrap;background:#111;color:#eee;padding:16px;border-radius:8px}</style></head><body>'
            .'<h1>'.e($title).'</h1>'.$body.'</body></html>';

        return response($html, $status)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
