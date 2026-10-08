<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usher extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'gender', 'date_of_birth', 'city', 'address', 'telegram',
        'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
        'education_level', 'institution', 'field_of_study', 'occupation', 'employer', 'employment_status',
        'years_experience', 'events_count', 'rating', 'status', 'available', 'availability_preference',
        'skills', 'languages', 'preferred_event_types', 'other_languages',
        'tshirt_size', 'shirt_size', 'trouser_size', 'shoe_size',
        'payment_method', 'bank_name', 'account_holder', 'account_number', 'telebirr_number',
        'id_type', 'id_number', 'instagram', 'facebook', 'linkedin', 'tiktok',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'available' => 'boolean',
            'skills' => 'array',
            'languages' => 'array',
            'preferred_event_types' => 'array',
            'rating' => 'float',
        ];
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(UsherExperience::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(UsherReference::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(UsherPhoto::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function refreshRating(): void
    {
        $average = $this->ratings()->avg('score');
        if ($average === null) {
            return;
        }

        $this->rating = round((float) $average, 1);
        $this->save();
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'city' => $this->city,
            'gender' => $this->gender ?? '',
            'experience' => $this->years_experience,
            'events' => $this->events_count,
            'rating' => (float) $this->rating,
            'skills' => $this->skills ?? [],
            'languages' => $this->languages ?? [],
            'status' => $this->status,
            'available' => $this->available,
        ];
    }

    public function presentPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'gender' => $this->gender ?? '',
            'experience' => $this->years_experience,
            'events' => $this->events_count,
            'rating' => (float) $this->rating,
            'skills' => $this->skills ?? [],
            'languages' => $this->languages ?? [],
            'preferredEvents' => $this->preferred_event_types ?? [],
            'experiences' => $this->experiences->map(fn (UsherExperience $experience) => [
                'event' => $experience->event_name,
                'client' => $experience->client,
                'role' => $experience->role,
            ])->all(),
        ];
    }
}
