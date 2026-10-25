<?php

namespace App\Support;

class PublicAppUrl
{
    public static function origin(): string
    {
        $raw = (string) config('app.frontend_url', 'http://localhost:3000');
        $first = trim(explode(',', $raw)[0]);

        return rtrim($first !== '' ? $first : 'http://localhost:3000', '/');
    }

    public static function path(string $path): string
    {
        return self::origin().'/'.ltrim($path, '/');
    }
}
