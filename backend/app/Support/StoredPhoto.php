<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StoredPhoto
{
    /** @var array<string, string>|null */
    private static ?array $index = null;

    public static function disk(): string
    {
        return (string) config('filesystems.photos', config('filesystems.default', 'local'));
    }

    public static function locate(string $path): ?string
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            return null;
        }

        if (Storage::disk(self::disk())->exists($path)) {
            return $path;
        }

        return self::localPath($path);
    }

    public static function response(string $path): Response
    {
        $path = ltrim($path, '/');
        abort_unless($path !== '', 404);

        if (Storage::disk(self::disk())->exists($path)) {
            return Storage::disk(self::disk())->response($path);
        }

        $full = self::localPath($path);
        abort_unless($full !== null, 404);

        return response()->file($full);
    }

    public static function delete(string $path): void
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            return;
        }

        $disk = Storage::disk(self::disk());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        $full = self::localPath($path);
        if ($full !== null) {
            unlink($full);
            self::$index = null;
        }
    }

    private static function localPath(string $path): ?string
    {
        foreach ([storage_path('app/private/'.$path), storage_path('app/'.$path), storage_path('app/public/'.$path)] as $full) {
            if (is_file($full)) {
                return $full;
            }
        }

        $name = basename($path);
        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        $found = self::index()[$name] ?? null;

        return $found !== null && is_file($found) ? $found : null;
    }

    /**
     * @return array<string, string>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        self::$index = [];
        $root = storage_path('app');
        if (is_dir($root)) {
            self::walk($root, 0);
        }

        return self::$index;
    }

    private static function walk(string $directory, int $depth): void
    {
        if ($depth > 6) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $directory.'/'.$entry;
            if (is_link($full)) {
                continue;
            }
            if (is_file($full)) {
                self::$index[$entry] ??= $full;
                continue;
            }
            if (is_dir($full)) {
                self::walk($full, $depth + 1);
            }
        }
    }
}
