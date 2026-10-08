<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'name', 'client', 'date_label', 'location', 'required_ushers', 'status',
        'call_time', 'end_time', 'compensation_label', 'transport_and_lunch', 'dress_code',
        'starts_on', 'ends_on', 'transport_provided', 'food_provided',
        'availability_token', 'client_token', 'rating_token',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'transport_provided' => 'boolean',
            'food_provided' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->availability_token ??= Str::random(40);
            $project->client_token ??= Str::random(40);
            $project->rating_token ??= Str::random(40);
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function present(): array
    {
        $confirmed = array_key_exists('confirmed_count', $this->attributes)
            ? (int) $this->confirmed_count
            : $this->assignments()->where('response', 'Confirmed')->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'client' => $this->client,
            'date' => $this->date_label,
            'location' => $this->location,
            'required' => $this->required_ushers,
            'confirmed' => $confirmed,
            'status' => $this->status,
            'callTime' => $this->call_time,
            'endTime' => $this->end_time,
            'compensation' => $this->compensation_label,
            'transport' => $this->transport_and_lunch,
            'dressCode' => $this->dress_code,
            ...$this->schedule(),
            'availabilityToken' => $this->availability_token,
            'clientToken' => $this->client_token,
            'ratingToken' => $this->rating_token,
        ];
    }

    public function presentPublic(): array
    {
        return [
            'name' => $this->name,
            'date' => $this->date_label,
            'location' => $this->location,
            'required' => $this->required_ushers,
            'callTime' => $this->call_time,
            'endTime' => $this->end_time,
            'compensation' => $this->compensation_label,
            'transport' => $this->transport_and_lunch,
            'dressCode' => $this->dress_code,
            ...$this->schedule(),
        ];
    }

    private function schedule(): array
    {
        return [
            'startsOn' => $this->starts_on?->toDateString(),
            'endsOn' => $this->ends_on?->toDateString(),
            'transportProvided' => (bool) $this->transport_provided,
            'foodProvided' => (bool) $this->food_provided,
        ];
    }

    public function syncSelection(array $usherIds): void
    {
        $this->assignments()->where('response', 'Confirmed')->update(['client_selected' => false]);
        if ($usherIds === []) {
            return;
        }

        $this->assignments()
            ->where('response', 'Confirmed')
            ->whereIn('usher_id', $usherIds)
            ->update(['client_selected' => true]);
    }
}
