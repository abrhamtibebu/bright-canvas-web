<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

class EventTime
{
    public static function label(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (preg_match('/am|pm/i', $value)) {
            return $value;
        }

        try {
            return Carbon::createFromFormat('H:i', $value)->format('g:i A');
        } catch (Throwable) {
            return $value;
        }
    }

    public static function dateLabel(string $value): string
    {
        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (Throwable) {
            return $value;
        }
    }
}
