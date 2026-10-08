<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'dressCode' => $project?->dress_code,
            'role' => $this->role,
            'response' => $this->response,
            'attendance' => $this->attendance,
            'clientSelected' => $this->client_selected,
        ];
    }
}
