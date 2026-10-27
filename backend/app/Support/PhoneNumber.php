<?php

namespace App\Support;

use App\Models\Usher;
use Closure;

class PhoneNumber
{
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    public static function canonical(?string $phone): ?string
    {
        $digits = self::digits($phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '251') && strlen($digits) >= 12) {
            return '251'.substr($digits, 3, 9);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '251'.substr($digits, 1);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '251'.$digits;
        }

        return $digits;
    }

    public static function e164(?string $phone): ?string
    {
        $canonical = self::canonical($phone);
        if ($canonical === null || strlen($canonical) < 10) {
            return null;
        }

        return '+'.$canonical;
    }

    public static function isTaken(string $phone, ?int $ignoreUsherId = null): bool
    {
        $canonical = self::canonical($phone);
        if ($canonical === null) {
            return false;
        }

        if ($ignoreUsherId) {
            $current = Usher::query()->find($ignoreUsherId);
            if ($current && self::canonical($current->phone) === $canonical) {
                return false;
            }
        }

        return Usher::query()
            ->when($ignoreUsherId, fn ($query) => $query->where('id', '!=', $ignoreUsherId))
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone'])
            ->contains(fn (Usher $usher) => self::canonical($usher->phone) === $canonical);
    }

    /**
     * @return list<Closure>
     */
    public static function uniqueRule(?int $ignoreUsherId = null): array
    {
        return [
            function (string $attribute, mixed $value, Closure $fail) use ($ignoreUsherId) {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }
                if (self::isTaken($value, $ignoreUsherId)) {
                    $fail('This phone number is already registered.');
                }
            },
        ];
    }
}
