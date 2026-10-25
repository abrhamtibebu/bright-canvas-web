<?php

namespace App\Console\Commands;

use App\Models\UsherPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncPhotosToLocal extends Command
{
    protected $signature = 'photos:sync-to-local
                            {--from= : Source disk name (default: configured photos disk / s3)}
                            {--to=local : Destination disk name}
                            {--force : Overwrite files that already exist locally}';

    protected $description = 'Download usher photo files from the remote photos disk (S3/R2) into local storage';

    public function handle(): int
    {
        $from = $this->option('from') ?: (string) config('filesystems.photos', 's3');
        $to = (string) $this->option('to');

        if ($from === $to) {
            $this->error('Source and destination disks must differ.');

            return self::FAILURE;
        }

        $source = Storage::disk($from);
        $target = Storage::disk($to);

        $paths = UsherPhoto::query()->orderBy('id')->pluck('path')->filter()->unique()->values();
        if ($paths->isEmpty()) {
            $paths = collect($source->allFiles('ushers'));
        }

        $this->info("Syncing {$paths->count()} photo path(s) from [{$from}] to [{$to}]…");

        $ok = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;

        foreach ($paths as $path) {
            $path = ltrim((string) $path, '/');
            if ($path === '') {
                continue;
            }

            if (! $this->option('force') && $target->exists($path)) {
                $skipped++;

                continue;
            }

            if (! $source->exists($path)) {
                $this->warn("Missing on {$from}: {$path}");
                $missing++;

                continue;
            }

            try {
                $stream = $source->readStream($path);
                if ($stream === false) {
                    throw new \RuntimeException('Could not open remote stream.');
                }
                $written = $target->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (! $written) {
                    throw new \RuntimeException('Could not write local file.');
                }
                $ok++;
                if ($ok % 20 === 0) {
                    $this->line("… {$ok} downloaded");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed {$path}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Done. downloaded={$ok} skipped={$skipped} missing={$missing} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
