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

    public static function spanLabel(string $start, string $end): string
    {
        try {
            $from = Carbon::parse($start)->startOfDay();
            $to = Carbon::parse($end)->startOfDay();
        } catch (Throwable) {
            return $start;
        }

        if ($from->isSameDay($to)) {
            return $from->format('M j, Y');
        }

        if ($from->isSameMonth($to) && $from->isSameYear($to)) {
            return $from->format('M j').'–'.$to->format('j, Y');
        }

        if ($from->isSameYear($to)) {
            return $from->format('M j').' – '.$to->format('M j, Y');
        }

        return $from->format('M j, Y').' – '.$to->format('M j, Y');
    }

    public static function minutes(string $value): ?int
    {
        try {
            $parsed = Carbon::createFromFormat('H:i', $value);
        } catch (Throwable) {
            return null;
        }

        if ($parsed === false) {
            return null;
        }

        return ($parsed->hour * 60) + $parsed->minute;
    }

    public static function logistics(bool $transport, bool $food): string
    {
        if ($transport && $food) {
            return 'Transport and food provided';
        }

        if ($transport) {
            return 'Transport provided, food not included';
        }

        if ($food) {
            return 'Food provided, transport not included';
        }

        return 'Transport and food not included';
    }
}
