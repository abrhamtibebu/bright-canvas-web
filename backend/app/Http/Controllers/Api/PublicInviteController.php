<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usher;
use App\Models\WorkspaceSetting;
use App\Support\PhoneNumber;
use App\Support\StoredPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class PublicInviteController extends Controller
{
    public const ACTIVE_MESSAGE = 'Your profile has been approved and is active.';

    public const AMEND_PARTIAL_MESSAGE = 'We found a profile that matches part of your details. Please review and update your full profile for approval.';

    public const AMEND_FOUND_MESSAGE = 'We found your profile. Please review and update your details for approval.';

    public const CREATE_MESSAGE = 'You haven’t created a Validity usher profile yet.';

    public const ACTIVE_CONTACT_MESSAGE = 'You already have an active profile with us. If you need an update, contact your admin.';

    public function show(string $token): JsonResponse
    {
        WorkspaceSetting::requireOpenToken($token);

        return response()->json(['ok' => true]);
    }

    public function lookup(Request $request, string $token): JsonResponse
    {
        WorkspaceSetting::requireOpenToken($token);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $byPhone = Usher::findByCanonicalPhone($data['phone']);
        $byName = Usher::findByNormalizedName($data['name']);
        $same = $byPhone && $byName && $byPhone->id === $byName->id;
        $existing = $byPhone ?? $byName;

        if ($same && $byPhone->status === 'Active') {
            return response()->json([
                'outcome' => 'active',
                'message' => self::ACTIVE_MESSAGE,
            ]);
        }

        if ($existing) {
            if (! $existing->profile_edit_token) {
                $existing->forceFill(['profile_edit_token' => Str::random(40)])->save();
            }
            $partial = ! $same;
            $session = $this->encodeSession([
                'outcome' => 'amend',
                'usherId' => $existing->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
            ]);
            $profile = $existing->presentForPublicEdit($existing->profile_edit_token);
            $profile['correctionSections'] = Usher::CORRECTION_SECTIONS;
            $profile['correctionNote'] = null;

            return response()->json([
                'outcome' => 'amend',
                'message' => $partial ? self::AMEND_PARTIAL_MESSAGE : self::AMEND_FOUND_MESSAGE,
                'session' => $session,
                'profile' => $profile,
            ]);
        }

        return response()->json([
            'outcome' => 'create',
            'message' => self::CREATE_MESSAGE,
            'session' => $this->encodeSession([
                'outcome' => 'create',
                'usherId' => null,
                'name' => $data['name'],
                'phone' => $data['phone'],
            ]),
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        WorkspaceSetting::requireOpenToken($token);
        $session = $this->decodeSession((string) $request->input('session'));
        $outcome = $session['outcome'] ?? '';

        if ($outcome === 'create') {
            return $this->createUsher($request);
        }
        if ($outcome === 'amend') {
            $usher = Usher::query()->findOrFail((int) $session['usherId']);

            return $this->amendUsher($request, $usher);
        }

        throw ValidationException::withMessages([
            'session' => ['This check session is not valid. Search again.'],
        ]);
    }

    private function createUsher(Request $request): JsonResponse
    {
        $active = Usher::findByCanonicalPhone((string) $request->input('phone', ''));
        if ($active && $active->status === 'Active') {
            throw ValidationException::withMessages([
                'phone' => [self::ACTIVE_CONTACT_MESSAGE],
            ]);
        }

        $data = $request->validate($this->registrationRules(true), $this->registrationMessages());
        $usher = Usher::query()->create($this->usherAttributes($data) + [
            'status' => 'Pending',
            'available' => true,
            'correction_sections' => null,
            'correction_note' => null,
        ]);
        $this->storePhotos($request, $usher, false);
        $this->storeExperiences($request, $usher, false);
        $this->storeReferences($request, $usher, false);

        return response()->json(['ok' => true], 201);
    }

    private function amendUsher(Request $request, Usher $usher): JsonResponse
    {
        $hasFront = $usher->idPhotoPath('front') !== null;
        $hasBack = $usher->idPhotoPath('back') !== null;
        $data = $request->validate($this->amendRules($usher->id, $hasFront, $hasBack), $this->registrationMessages());
        $usher->update($this->usherAttributes($data) + [
            'status' => 'Pending',
            'available' => true,
            'correction_sections' => null,
            'correction_note' => null,
        ]);
        $this->storePhotos($request, $usher, true);
        $this->storeExperiences($request, $usher, $request->filled('exEvent0'));
        $this->storeReferences($request, $usher, $request->filled('rName0'));

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationRules(bool $requirePhotos): array
    {
        $adultBy = now()->subYears(18)->toDateString();

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:50', ...PhoneNumber::uniqueRule()],
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
            'photo1' => [$requirePhotos ? 'required' : 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'photo2' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'photo3' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'bank' => ['required', 'in:CBE'],
            'holder' => ['required', 'string', 'max:255'],
            'acct' => ['required', 'string', 'max:100', 'regex:/^1000\d+$/'],
            'telebirr' => ['nullable', 'string', 'max:50'],
            'idType' => ['required', 'string', 'max:100'],
            'idNo' => ['nullable', 'string', 'max:100'],
            'idPhotoFront' => [$requirePhotos ? 'required' : 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'idPhotoBack' => [$requirePhotos ? 'required' : 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'ig' => ['nullable', 'string', 'max:2048'],
            'fb' => ['nullable', 'string', 'max:2048'],
            'li' => ['nullable', 'string', 'max:2048'],
            'tt' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function amendRules(int $usherId, bool $hasFront, bool $hasBack): array
    {
        $rules = $this->registrationRules(false);
        $rules['phone'] = ['required', 'string', 'max:50', ...PhoneNumber::uniqueRule($usherId)];
        $rules['idPhotoFront'] = [$hasFront ? 'nullable' : 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'];
        $rules['idPhotoBack'] = [$hasBack ? 'nullable' : 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function registrationMessages(): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function usherAttributes(array $data): array
    {
        $blank = fn (?string $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        return [
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
            'instagram' => $blank($data['ig'] ?? null),
            'facebook' => $blank($data['fb'] ?? null),
            'linkedin' => $blank($data['li'] ?? null),
            'tiktok' => $blank($data['tt'] ?? null),
        ];
    }

    private function storePhotos(Request $request, Usher $usher, bool $replace): void
    {
        if ($replace && $request->hasFile('photo1')) {
            foreach ($usher->photos()->where('kind', 'profile')->get() as $photo) {
                StoredPhoto::delete($photo->path);
                $photo->delete();
            }
        }
        if ($replace && ($request->hasFile('photo2') || $request->hasFile('photo3'))) {
            foreach ($usher->photos()->where('kind', 'additional')->get() as $photo) {
                StoredPhoto::delete($photo->path);
                $photo->delete();
            }
        }
        if ($replace && $request->hasFile('idPhotoFront')) {
            foreach ($usher->photos()->whereIn('kind', ['id_front', 'id'])->get() as $photo) {
                StoredPhoto::delete($photo->path);
                $photo->delete();
            }
        }
        if ($replace && $request->hasFile('idPhotoBack')) {
            foreach ($usher->photos()->where('kind', 'id_back')->get() as $photo) {
                StoredPhoto::delete($photo->path);
                $photo->delete();
            }
        }

        foreach ([
            'photo1' => 'profile',
            'photo2' => 'additional',
            'photo3' => 'additional',
            'idPhotoFront' => 'id_front',
            'idPhotoBack' => 'id_back',
        ] as $field => $kind) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $path = StoredPhoto::store($request->file($field), 'ushers/'.$usher->id);
            $usher->photos()->create(['path' => $path, 'kind' => $kind]);
        }
    }

    private function storeExperiences(Request $request, Usher $usher, bool $replace): void
    {
        if ($replace) {
            $usher->experiences()->delete();
        }
        for ($i = 0; $i < 20; $i++) {
            $event = $request->input("exEvent{$i}");
            if (! $event) {
                continue;
            }
            $usher->experiences()->create([
                'event_name' => $event,
                'client' => $request->input("exClient{$i}"),
                'role' => $request->input("exRole{$i}"),
                'event_type' => $request->input("exType{$i}"),
            ]);
        }
    }

    private function storeReferences(Request $request, Usher $usher, bool $replace): void
    {
        if ($replace) {
            $usher->references()->delete();
        }
        for ($i = 0; $i < 20; $i++) {
            $name = $request->input("rName{$i}");
            if (! $name) {
                continue;
            }
            $usher->references()->create([
                'name' => $name,
                'organization' => $request->input("rOrg{$i}"),
                'relationship' => $request->input("rRel{$i}"),
                'phone' => $request->input("rPhone{$i}"),
                'email' => $request->input("rEmail{$i}"),
                'notes' => $request->input("rNotes{$i}"),
            ]);
        }
    }

    /**
     * @param  array{outcome: string, usherId: ?int, name: string, phone: string}  $payload
     */
    private function encodeSession(array $payload): string
    {
        $payload['exp'] = now()->addDay()->unix();

        return Crypt::encryptString((string) json_encode($payload));
    }

    /**
     * @return array{outcome: string, usherId: mixed, name: mixed, phone: mixed, exp: mixed}
     */
    private function decodeSession(string $session): array
    {
        if ($session === '') {
            throw ValidationException::withMessages([
                'session' => ['This check session expired. Search again.'],
            ]);
        }
        try {
            $data = json_decode(Crypt::decryptString($session), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException|Throwable) {
            throw ValidationException::withMessages([
                'session' => ['This check session expired. Search again.'],
            ]);
        }
        if (! is_array($data) || (int) ($data['exp'] ?? 0) < now()->unix()) {
            throw ValidationException::withMessages([
                'session' => ['This check session expired. Search again.'],
            ]);
        }

        return $data;
    }
}
