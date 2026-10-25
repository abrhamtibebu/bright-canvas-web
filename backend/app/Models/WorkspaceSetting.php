<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkspaceSetting extends Model
{
    protected $fillable = [
        'company',
        'workspace',
        'timezone',
        'currency',
        'registration_token',
        'registration_open',
    ];

    protected function casts(): array
    {
        return [
            'registration_open' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkspaceSetting $setting) {
            $setting->registration_token ??= Str::random(40);
            $setting->registration_open ??= true;
        });
    }

    public static function requireOpenToken(string $token): self
    {
        $setting = static::query()->where('registration_token', $token)->firstOrFail();
        abort_unless((bool) $setting->registration_open, 404);

        return $setting;
    }

    public function present(): array
    {
        return [
            'company' => $this->company,
            'workspace' => $this->workspace,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'registrationToken' => $this->registration_token,
            'registrationOpen' => (bool) $this->registration_open,
        ];
    }
}
