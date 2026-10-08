<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Assignment extends Model
{
    protected $fillable = [
        'project_id', 'usher_id', 'role', 'response', 'attendance', 'client_selected',
    ];

    protected function casts(): array
    {
        return [
            'client_selected' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Assignment $assignment) {
            $assignment->confirmation_token ??= Str::random(40);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function usher(): BelongsTo
    {
        return $this->belongsTo(Usher::class);
    }

    public function present(): array
    {
        $project = $this->project;

        return [
            'id' => $this->id,
            'projectId' => $this->project_id,
            'usherId' => $this->usher_id,
            'projectName' => $project?->name,
            'projectDate' => $project?->date_label,
            'location' => $project?->location,
            'callTime' => $project?->call_time,
            'endTime' => $project?->end_time,
            'compensation' => $project?->compensation_label,
            'transport' => $project?->transport_and_lunch,
            'transportProvided' => (bool) ($project?->transport_provided ?? true),
            'foodProvided' => (bool) ($project?->food_provided ?? true),
            'dressCode' => $project?->dress_code,
            'role' => $this->role,
            'response' => $this->response,
            'attendance' => $this->attendance,
            'clientSelected' => $this->client_selected,
            'confirmationToken' => $this->ensureConfirmationToken(),
        ];
    }

    public function ensureConfirmationToken(): string
    {
        if (is_string($this->confirmation_token) && $this->confirmation_token !== '') {
            return $this->confirmation_token;
        }

        $this->confirmation_token = Str::random(40);
        $this->save();

        return $this->confirmation_token;
    }
}
