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
    ];

    protected static function booted(): void
    {
        static::creating(function (WorkspaceSetting $setting) {
            $setting->registration_token ??= Str::random(40);
        });
    }

    public function present(): array
    {
        return [
            'company' => $this->company,
            'workspace' => $this->workspace,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'registrationToken' => $this->registration_token,
        ];
    }
}
