<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportRemoteToSqlite extends Command
{
    protected $signature = 'db:import-remote-to-sqlite
                            {--path= : Absolute path to the SQLite file (default: database/database.sqlite)}
                            {--fresh : Recreate the SQLite schema with migrations before importing}';

    protected $description = 'Copy every table from the configured pgsql (Render) connection into a local SQLite file';

    /** @var list<string> */
    private array $tables = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens',
        'workspace_settings',
        'ushers',
        'usher_experiences',
        'usher_references',
        'usher_photos',
        'projects',
        'assignments',
        'ratings',
        'migrations',
    ];

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('database.sqlite');
        $source = 'pgsql';
        $target = 'sqlite';

        if (! extension_loaded('pdo_pgsql') || ! extension_loaded('pdo_sqlite')) {
            $this->error('Both pdo_pgsql and pdo_sqlite extensions are required.');

            return self::FAILURE;
        }

        config([
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => $path,
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge($target);

        $this->info('Source: pgsql (DATABASE_URL / Render)');
        $this->info('Target: '.$path);

        try {
            DB::connection($source)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to remote pgsql: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh') || ! file_exists($path) || filesize($path) === 0) {
            if (file_exists($path)) {
                $backup = $path.'.bak-'.date('Ymd-His');
                copy($path, $backup);
                $this->warn('Backed up existing SQLite to '.$backup);
                unlink($path);
            }
            touch($path);
            DB::purge($target);
            $this->call('migrate', [
                '--database' => $target,
                '--force' => true,
            ]);
        }

        DB::connection($target)->statement('PRAGMA foreign_keys = OFF');
        DB::connection($target)->statement('PRAGMA defer_foreign_keys = ON');

        $copied = 0;
        foreach ($this->tables as $table) {
            if (! Schema::connection($source)->hasTable($table)) {
                $this->warn("Skip missing source table: {$table}");

                continue;
            }
            if (! Schema::connection($target)->hasTable($table)) {
                $this->warn("Skip missing target table: {$table}");

                continue;
            }

            DB::connection($target)->table($table)->delete();

            $rows = DB::connection($source)->table($table)->orderByRaw('1')->get();
            $count = 0;
            foreach ($rows->chunk(200) as $chunk) {
                $payload = $chunk->map(function ($row) {
                    $data = (array) $row;
                    foreach ($data as $key => $value) {
                        if (is_bool($value)) {
                            $data[$key] = $value ? 1 : 0;
                        } elseif ($value instanceof \DateTimeInterface) {
                            $data[$key] = $value->format('Y-m-d H:i:s');
                        } elseif (is_resource($value)) {
                            $data[$key] = stream_get_contents($value);
                        }
                    }

                    return $data;
                })->all();

                if ($payload !== []) {
                    DB::connection($target)->table($table)->insert($payload);
                    $count += count($payload);
                }
            }

            $this->line(sprintf('%-28s %d rows', $table, $count));
            $copied += $count;
        }

        DB::connection($target)->statement('PRAGMA foreign_keys = ON');

        // Keep SQLite autoincrement counters aligned with imported primary keys.
        foreach (['users', 'ushers', 'projects', 'assignments', 'ratings', 'usher_photos', 'usher_experiences', 'usher_references', 'personal_access_tokens', 'jobs', 'failed_jobs'] as $table) {
            if (! Schema::connection($target)->hasTable($table) || ! Schema::connection($target)->hasColumn($table, 'id')) {
                continue;
            }
            $max = (int) DB::connection($target)->table($table)->max('id');
            if ($max <= 0) {
                continue;
            }
            DB::connection($target)->table('sqlite_sequence')->where('name', $table)->delete();
            DB::connection($target)->table('sqlite_sequence')->insert(['name' => $table, 'seq' => $max]);
        }

        $this->info("Imported {$copied} rows into {$path}");

        return self::SUCCESS;
    }
}
