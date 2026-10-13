<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usher;
use App\Models\UsherPhoto;
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
        $adultBy = now()->subYears(18)->toDateString();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:Female,Male'],
            'dob' => ['required', 'date', 'before_or_equal:'.$adultBy],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telegram' => ['nullable', 'string', 'max:100'],
            'ecName' => ['required', 'string', 'max:255'],
            'ecRel' => ['required', 'string', 'max:100'],
            'ecPhone' => ['required', 'string', 'max:50'],
            'edu' => ['nullable', 'string', 'max:100'],
            'inst' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'max:255'],
            'occ' => ['nullable', 'string', 'max:255'],
            'employer' => ['nullable', 'string', 'max:255'],
            'empStatus' => ['nullable', 'string', 'max:100'],
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
            'tshirt' => ['nullable', 'string', 'max:10'],
            'shirt' => ['nullable', 'string', 'max:10'],
            'trouser' => ['nullable', 'string', 'max:20'],
            'shoe' => ['nullable', 'string', 'max:20'],
            'photo1' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'photo2' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'photo3' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'bank' => ['required', 'in:CBE'],
            'holder' => ['required', 'string', 'max:255'],
            'acct' => ['required', 'string', 'max:100', 'regex:/^1000\d+$/'],
            'telebirr' => ['nullable', 'string', 'max:50'],
            'idType' => ['required', 'string', 'max:100'],
            'idNo' => ['nullable', 'string', 'max:100'],
            'idPhotoFront' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'idPhotoBack' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'ig' => ['nullable', 'string', 'max:255'],
            'fb' => ['nullable', 'string', 'max:255'],
            'li' => ['nullable', 'string', 'max:255'],
            'tt' => ['nullable', 'string', 'max:255'],
        ], [
            'dob.before_or_equal' => 'Ushers must be 18 or older.',
            'acct.regex' => 'CBE account numbers must start with 1000 and contain digits only.',
            'photo1.max' => 'Each photo must be 2 MB or smaller.',
            'photo2.max' => 'Each photo must be 2 MB or smaller.',
            'photo3.max' => 'Each photo must be 2 MB or smaller.',
            'idPhotoFront.max' => 'The ID front photo must be 2 MB or smaller.',
            'idPhotoBack.max' => 'The ID back photo must be 2 MB or smaller.',
        ]);

        $usher->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'gender' => $data['gender'],
            'date_of_birth' => $data['dob'],
            'city' => $data['city'],
            'address' => $data['address'],
            'telegram' => $data['telegram'] ?? null,
            'emergency_contact_name' => $data['ecName'],
            'emergency_contact_relationship' => $data['ecRel'],
            'emergency_contact_phone' => $data['ecPhone'],
            'education_level' => $data['edu'] ?? null,
            'institution' => $data['inst'] ?? null,
            'field_of_study' => $data['field'] ?? null,
            'occupation' => $data['occ'] ?? null,
            'employer' => $data['employer'] ?? null,
            'employment_status' => $data['empStatus'] ?? null,
            'years_experience' => $data['years'] ?? 0,
            'events_count' => $data['events'] ?? 0,
            'skills' => $data['skills'] ?? [],
            'languages' => $data['languages'] ?? [],
            'preferred_event_types' => $data['prefs'] ?? [],
            'other_languages' => $data['otherLang'] ?? null,
            'availability_preference' => $data['availability'] ?? null,
            'tshirt_size' => $data['tshirt'] ?? null,
            'shirt_size' => $data['shirt'] ?? null,
            'trouser_size' => $data['trouser'] ?? null,
            'shoe_size' => $data['shoe'] ?? null,
            'payment_method' => 'CBE',
            'bank_name' => 'CBE',
            'account_holder' => $data['holder'],
            'account_number' => $data['acct'],
            'telebirr_number' => $data['telebirr'] ?? null,
            'id_type' => $data['idType'],
            'id_number' => $data['idNo'] ?? null,
            'instagram' => $data['ig'] ?? null,
            'facebook' => $data['fb'] ?? null,
            'linkedin' => $data['li'] ?? null,
            'tiktok' => $data['tt'] ?? null,
            'status' => 'Pending',
        ]);

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

        return response()->json(['ok' => true]);
    }

    public function photo(string $token, UsherPhoto $photo): SymfonyResponse
    {
        $usher = $this->findByToken($token);
        abort_unless($photo->usher_id === $usher->id, 404);

        return StoredPhoto::response($photo->path);
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
        ];
    }
}
