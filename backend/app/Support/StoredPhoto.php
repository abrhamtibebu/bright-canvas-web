<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoredPhoto
{
    /** @var array<string, string>|null */
    private static ?array $index = null;

    public static function locate(string $path): ?string
    {
        $path = ltrim($path, '/');
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

    public static function response(string $path): BinaryFileResponse
    {
        $full = self::locate($path);
        abort_unless($full !== null, 404);

        return response()->file($full);
    }

    public static function delete(string $path): void
    {
        $full = self::locate($path);
        if ($full !== null) {
            unlink($full);
        }
    }
}
