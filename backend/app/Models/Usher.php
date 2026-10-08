<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\StoredPhoto;

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

    public function photoPaths(): array
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();

        return $photos
            ->sortBy(fn (UsherPhoto $photo) => $photo->kind === 'profile' ? 0 : 1)
            ->filter(fn (UsherPhoto $photo) => StoredPhoto::locate($photo->path) !== null)
            ->values()
            ->map(fn (UsherPhoto $photo) => "/api/ushers/{$this->id}/photos/{$photo->id}")
            ->all();
    }

    public function publicPhotoPaths(string $token): array
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();

        return $photos
            ->sortBy(fn (UsherPhoto $photo) => $photo->kind === 'profile' ? 0 : 1)
            ->filter(fn (UsherPhoto $photo) => StoredPhoto::locate($photo->path) !== null)
            ->values()
            ->map(fn (UsherPhoto $photo) => "/api/public/photos/{$token}/{$photo->id}")
            ->all();
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
            'email' => $this->email,
            'city' => $this->city,
            'address' => $this->address,
            'gender' => $this->gender ?? '',
            'dateOfBirth' => $this->date_of_birth?->format('M j, Y'),
            'telegram' => $this->telegram,
            'emergencyContactName' => $this->emergency_contact_name,
            'emergencyContactRelationship' => $this->emergency_contact_relationship,
            'emergencyContactPhone' => $this->emergency_contact_phone,
            'educationLevel' => $this->education_level,
            'institution' => $this->institution,
            'fieldOfStudy' => $this->field_of_study,
            'occupation' => $this->occupation,
            'employer' => $this->employer,
            'employmentStatus' => $this->employment_status,
            'experience' => $this->years_experience,
            'events' => $this->events_count,
            'rating' => (float) $this->rating,
            'availability' => $this->availability_preference,
            'skills' => $this->skills ?? [],
            'languages' => $this->languages ?? [],
            'otherLanguages' => $this->other_languages,
            'preferredEvents' => $this->preferred_event_types ?? [],
            'tshirtSize' => $this->tshirt_size,
            'shirtSize' => $this->shirt_size,
            'trouserSize' => $this->trouser_size,
            'shoeSize' => $this->shoe_size,
            'paymentMethod' => $this->payment_method,
            'bankName' => $this->bank_name,
            'accountHolder' => $this->account_holder,
            'accountNumber' => $this->account_number,
            'telebirrNumber' => $this->telebirr_number,
            'idType' => $this->id_type,
            'idNumber' => $this->id_number,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'linkedin' => $this->linkedin,
            'tiktok' => $this->tiktok,
            'status' => $this->status,
            'available' => $this->available,
            'photos' => $this->photoPaths(),
            'experiences' => $this->experienceRows(),
            'references' => $this->referenceRows(),
        ];
    }

    public function presentPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'gender' => $this->gender ?? '',
            'educationLevel' => $this->education_level,
            'institution' => $this->institution,
            'fieldOfStudy' => $this->field_of_study,
            'occupation' => $this->occupation,
            'employer' => $this->employer,
            'employmentStatus' => $this->employment_status,
            'experience' => $this->years_experience,
            'events' => $this->events_count,
            'rating' => (float) $this->rating,
            'availability' => $this->availability_preference,
            'skills' => $this->skills ?? [],
            'languages' => $this->languages ?? [],
            'otherLanguages' => $this->other_languages,
            'preferredEvents' => $this->preferred_event_types ?? [],
            'tshirtSize' => $this->tshirt_size,
            'shirtSize' => $this->shirt_size,
            'trouserSize' => $this->trouser_size,
            'shoeSize' => $this->shoe_size,
            'experiences' => $this->experienceRows(),
        ];
    }

    private function experienceRows(): array
    {
        $experiences = $this->relationLoaded('experiences') ? $this->experiences : $this->experiences()->get();

        return $experiences->map(fn (UsherExperience $experience) => [
            'event' => $experience->event_name,
            'client' => $experience->client,
            'role' => $experience->role,
            'eventType' => $experience->event_type,
        ])->values()->all();
    }

    private function referenceRows(): array
    {
        $references = $this->relationLoaded('references') ? $this->references : $this->references()->get();

        return $references->map(fn (UsherReference $reference) => [
            'name' => $reference->name,
            'organization' => $reference->organization,
            'relationship' => $reference->relationship,
            'phone' => $reference->phone,
            'email' => $reference->email,
            'notes' => $reference->notes,
        ])->values()->all();
    }
}
