<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usher;
use App\Models\UsherPhoto;
use App\Support\PhoneNumber;
use App\Support\StoredPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicUsherProfileController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $usher = $this->findByToken($token);
        $usher->load(['photos', 'experiences', 'references']);

        return response()->json($this->presentPublicEdit($usher, $token));
    }

    public function update(Request $request, string $token): JsonResponse
    {
        $usher = $this->findByToken($token);
        $sections = $usher->correctionSectionsForUpdate($request);
        $adultBy = now()->subYears(18)->toDateString();
        $hasFront = $usher->idPhotoPath('front') !== null;
        $hasBack = $usher->idPhotoPath('back') !== null;

        $rules = [];
        if (in_array('about', $sections, true)) {
            $rules = array_merge($rules, [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'phone' => ['required', 'string', 'max:50', ...PhoneNumber::uniqueRule($usher->id)],
                'gender' => ['required', 'in:Female,Male'],
                'dob' => ['required', 'date', 'before_or_equal:'.$adultBy],
                'city' => ['required', 'string', 'max:100'],
                'address' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'telegram' => ['nullable', 'string', 'max:100'],
            ]);
        }
        if (in_array('emergency', $sections, true)) {
            $rules = array_merge($rules, [
                'ecName' => ['required', 'string', 'max:255'],
                'ecRel' => ['required', 'string', 'max:100'],
                'ecPhone' => ['required', 'string', 'max:50'],
            ]);
        }
        if (in_array('background', $sections, true)) {
            $rules = array_merge($rules, [
                'edu' => ['nullable', 'string', 'max:100'],
                'inst' => ['nullable', 'string', 'max:255'],
                'field' => ['nullable', 'string', 'max:255'],
                'occ' => ['nullable', 'string', 'max:255'],
                'employer' => ['nullable', 'string', 'max:255'],
                'empStatus' => ['nullable', 'string', 'max:100'],
            ]);
        }
        if (in_array('skills', $sections, true)) {
            $rules = array_merge($rules, [
                'languages' => ['nullable', 'array'],
                'languages.*' => ['string', 'max:50'],
                'otherLang' => ['nullable', 'string', 'max:255'],
                'years' => ['nullable', 'integer', 'min:0'],
                'events' => ['nullable', 'integer', 'min:0'],
                'skills' => ['nullable', 'array'],
                'skills.*' => ['string', 'max:80'],
                'prefs' => ['nullable', 'array'],
                'prefs.*' => ['string', 'max:80'],
                'availability' => ['nullable', 'string', 'max:100'],
            ]);
        }
        if (in_array('photos', $sections, true)) {
            $rules = array_merge($rules, [
                'tshirt' => ['nullable', 'string', 'max:10'],
                'shirt' => ['nullable', 'string', 'max:10'],
                'trouser' => ['nullable', 'string', 'max:20'],
                'shoe' => ['nullable', 'string', 'max:20'],
                'photo1' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
                'photo2' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
                'photo3' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            ]);
        }
        if (in_array('payment', $sections, true)) {
            $rules = array_merge($rules, [
                'bank' => ['required', 'in:CBE'],
                'holder' => ['required', 'string', 'max:255'],
                'acct' => ['required', 'string', 'max:100', 'regex:/^1000\d+$/'],
                'telebirr' => ['nullable', 'string', 'max:50'],
                'idType' => ['required', 'string', 'max:100'],
                'idNo' => ['nullable', 'string', 'max:100'],
                'idPhotoFront' => [$hasFront ? 'nullable' : 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
                'idPhotoBack' => [$hasBack ? 'nullable' : 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            ]);
        }
        if (in_array('social', $sections, true)) {
            $rules = array_merge($rules, [
                'ig' => ['nullable', 'string', 'max:2048'],
                'fb' => ['nullable', 'string', 'max:2048'],
                'li' => ['nullable', 'string', 'max:2048'],
                'tt' => ['nullable', 'string', 'max:2048'],
            ]);
        }

        $data = $request->validate($rules, [
            'phone.unique' => 'This phone number is already registered.',
            'dob.before_or_equal' => 'Ushers must be 18 or older.',
            'acct.regex' => 'CBE account numbers must start with 1000 and contain digits only.',
            'photo1.max' => 'Each photo must be 2 MB or smaller.',
            'photo2.max' => 'Each photo must be 2 MB or smaller.',
            'photo3.max' => 'Each photo must be 2 MB or smaller.',
            'idPhotoFront.max' => 'The ID front photo must be 2 MB or smaller.',
            'idPhotoBack.max' => 'The ID back photo must be 2 MB or smaller.',
            'idPhotoFront.required' => 'Attach a clear photo of the front of your ID.',
            'idPhotoBack.required' => 'Attach a clear photo of the back of your ID.',
        ]);

        $updates = [
            'status' => 'Pending',
            'correction_sections' => null,
            'correction_note' => null,
        ];

        if (in_array('about', $sections, true)) {
            $updates = array_merge($updates, [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $this->blank($data['email'] ?? null),
                'gender' => $data['gender'],
                'date_of_birth' => $data['dob'],
                'city' => $data['city'],
                'address' => $data['address'],
                'telegram' => $this->blank($data['telegram'] ?? null),
            ]);
        }
        if (in_array('emergency', $sections, true)) {
            $updates = array_merge($updates, [
                'emergency_contact_name' => $data['ecName'],
                'emergency_contact_relationship' => $data['ecRel'],
                'emergency_contact_phone' => $data['ecPhone'],
            ]);
        }
        if (in_array('background', $sections, true)) {
            $updates = array_merge($updates, [
                'education_level' => $this->blank($data['edu'] ?? null),
                'institution' => $this->blank($data['inst'] ?? null),
                'field_of_study' => $this->blank($data['field'] ?? null),
                'occupation' => $this->blank($data['occ'] ?? null),
                'employer' => $this->blank($data['employer'] ?? null),
                'employment_status' => $this->blank($data['empStatus'] ?? null),
            ]);
        }
        if (in_array('skills', $sections, true)) {
            $updates = array_merge($updates, [
                'years_experience' => $data['years'] ?? 0,
                'events_count' => $data['events'] ?? 0,
                'skills' => $data['skills'] ?? [],
                'languages' => $data['languages'] ?? [],
                'preferred_event_types' => $data['prefs'] ?? [],
                'other_languages' => $this->blank($data['otherLang'] ?? null),
                'availability_preference' => $this->blank($data['availability'] ?? null),
            ]);
        }
        if (in_array('photos', $sections, true)) {
            $updates = array_merge($updates, [
                'tshirt_size' => $this->blank($data['tshirt'] ?? null),
                'shirt_size' => $this->blank($data['shirt'] ?? null),
                'trouser_size' => $this->blank($data['trouser'] ?? null),
                'shoe_size' => $this->blank($data['shoe'] ?? null),
            ]);
        }
        if (in_array('payment', $sections, true)) {
            $updates = array_merge($updates, [
                'payment_method' => 'CBE',
                'bank_name' => 'CBE',
                'account_holder' => $data['holder'],
                'account_number' => $data['acct'],
                'telebirr_number' => $this->blank($data['telebirr'] ?? null),
                'id_type' => $data['idType'],
                'id_number' => $this->blank($data['idNo'] ?? null),
            ]);
        }
        if (in_array('social', $sections, true)) {
            $updates = array_merge($updates, [
                'instagram' => $this->blank($data['ig'] ?? null),
                'facebook' => $this->blank($data['fb'] ?? null),
                'linkedin' => $this->blank($data['li'] ?? null),
                'tiktok' => $this->blank($data['tt'] ?? null),
            ]);
        }

        $usher->update($updates);

        if (in_array('photos', $sections, true)) {
            foreach (['photo1' => 'profile', 'photo2' => 'additional', 'photo3' => 'additional'] as $field => $kind) {
                if (! $request->hasFile($field)) {
                    continue;
                }
                if ($kind === 'profile') {
                    $existing = $usher->photos()->where('kind', 'profile')->get();
                    foreach ($existing as $photo) {
                        StoredPhoto::delete($photo->path);
                        $photo->delete();
                    }
                }
                $path = StoredPhoto::store($request->file($field), 'ushers/'.$usher->id);
                $usher->photos()->create(['path' => $path, 'kind' => $kind]);
            }
        }

        if (in_array('payment', $sections, true)) {
            foreach (['idPhotoFront' => 'id_front', 'idPhotoBack' => 'id_back'] as $field => $kind) {
                if (! $request->hasFile($field)) {
                    continue;
                }
                $existingKinds = $kind === 'id_front' ? ['id_front', 'id'] : ['id_back'];
                $existing = $usher->photos()->whereIn('kind', $existingKinds)->get();
                foreach ($existing as $photo) {
                    StoredPhoto::delete($photo->path);
                    $photo->delete();
                }
                $path = StoredPhoto::store($request->file($field), 'ushers/'.$usher->id);
                $usher->photos()->create(['path' => $path, 'kind' => $kind]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function photo(string $token, UsherPhoto $photo): SymfonyResponse
    {
        $usher = $this->findByToken($token);
        abort_unless($photo->usher_id === $usher->id, 404);

        return StoredPhoto::response($photo->path);
    }

    private function blank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function findByToken(string $token): Usher
    {
        return Usher::query()
            ->where('profile_edit_token', $token)
            ->whereNotNull('profile_edit_token')
            ->firstOrFail();
    }

    private function presentPublicEdit(Usher $usher, string $token): array
    {
        $photos = $usher->photos
            ->filter(fn (UsherPhoto $photo) => ! Usher::isIdPhotoKind($photo->kind))
            ->sortBy(fn (UsherPhoto $photo) => $photo->kind === 'profile' ? 0 : 1)
            ->filter(fn (UsherPhoto $photo) => StoredPhoto::locate($photo->path) !== null)
            ->values()
            ->map(fn (UsherPhoto $photo) => "/api/public/profile/{$token}/photos/{$photo->id}")
            ->all();

        $idFront = $usher->photos->first(fn (UsherPhoto $photo) => in_array($photo->kind, ['id_front', 'id'], true));
        $idBack = $usher->photos->first(fn (UsherPhoto $photo) => $photo->kind === 'id_back');
        $idFrontPath = $idFront && StoredPhoto::locate($idFront->path) !== null
            ? "/api/public/profile/{$token}/photos/{$idFront->id}"
            : null;
        $idBackPath = $idBack && StoredPhoto::locate($idBack->path) !== null
            ? "/api/public/profile/{$token}/photos/{$idBack->id}"
            : null;

        return [
            'id' => $usher->id,
            'name' => $usher->name,
            'phone' => $usher->phone,
            'email' => $usher->email,
            'city' => $usher->city,
            'address' => $usher->address,
            'gender' => $usher->gender ?? '',
            'dob' => $usher->date_of_birth?->format('Y-m-d'),
            'telegram' => $usher->telegram,
            'emergencyContactName' => $usher->emergency_contact_name,
            'emergencyContactRelationship' => $usher->emergency_contact_relationship,
            'emergencyContactPhone' => $usher->emergency_contact_phone,
            'educationLevel' => $usher->education_level,
            'institution' => $usher->institution,
            'fieldOfStudy' => $usher->field_of_study,
            'occupation' => $usher->occupation,
            'employer' => $usher->employer,
            'employmentStatus' => $usher->employment_status,
            'experience' => $usher->years_experience,
            'events' => $usher->events_count,
            'availability' => $usher->availability_preference,
            'skills' => $usher->skills ?? [],
            'languages' => $usher->languages ?? [],
            'otherLanguages' => $usher->other_languages,
            'preferredEvents' => $usher->preferred_event_types ?? [],
            'tshirtSize' => $usher->tshirt_size,
            'shirtSize' => $usher->shirt_size,
            'trouserSize' => $usher->trouser_size,
            'shoeSize' => $usher->shoe_size,
            'bankName' => $usher->bank_name,
            'accountHolder' => $usher->account_holder,
            'accountNumber' => $usher->account_number,
            'telebirrNumber' => $usher->telebirr_number,
            'idType' => $usher->id_type,
            'idNumber' => $usher->id_number,
            'idPhoto' => $idFrontPath,
            'idPhotoFront' => $idFrontPath,
            'idPhotoBack' => $idBackPath,
            'instagram' => $usher->instagram,
            'facebook' => $usher->facebook,
            'linkedin' => $usher->linkedin,
            'tiktok' => $usher->tiktok,
            'status' => $usher->status,
            'photos' => $photos,
            'correctionSections' => $usher->correctionSections(),
            'correctionNote' => $usher->correction_note,
        ];
    }
}
