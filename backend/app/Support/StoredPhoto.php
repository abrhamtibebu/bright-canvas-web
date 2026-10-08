<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoredPhoto
{
    public static function locate(string $path): ?string
    {
        $path = ltrim($path, '/');
        foreach ([storage_path('app/private/'.$path), storage_path('app/'.$path)] as $full) {
            if (is_file($full)) {
                return $full;
            }
        }

        return null;
    }

    public static function response(string $path): BinaryFileResponse
    {
        $full = self::locate($path);
        abort_unless($full !== null, 404);

        return response()->file($full);
    }
}
