<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usher;
use App\Models\UsherPhoto;
use App\Support\StoredPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UsherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ushers = Usher::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->string('search').'%');
            })
            ->when($request->filled('status') && $request->string('status') !== 'All statuses', function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('city') && $request->string('city') !== 'All cities', function ($query) use ($request) {
                $query->where('city', $request->string('city'));
            })
            ->when($request->boolean('available'), function ($query) {
                $query->where('available', true);
            })
            ->with(['photos', 'experiences', 'references'])
            ->orderBy('id')
            ->get()
            ->filter(function (Usher $usher) use ($request) {
                $skill = $request->string('skill')->toString();
                if ($skill === '' || $skill === 'All skills') {
                    return true;
                }

                return in_array($skill, $usher->skills ?? [], true);
            })
            ->map(fn (Usher $usher) => $usher->present())
            ->values();

        return response()->json($ushers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $usher = Usher::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? 'Addis Ababa',
            'gender' => 'Not specified',
            'skills' => ['Registration'],
            'languages' => ['Amharic'],
            'status' => 'Pending',
            'available' => true,
        ]);

        return response()->json($usher->present(), 201);
    }

    public function show(Usher $usher): JsonResponse
    {
        $usher->load(['photos', 'experiences', 'references']);

        return response()->json($usher->present());
    }

    public function photo(Usher $usher, UsherPhoto $photo): SymfonyResponse
    {
        abort_unless($photo->usher_id === $usher->id, 404);

        return StoredPhoto::response($photo->path);
    }

    public function storePhoto(Request $request, Usher $usher): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        $path = StoredPhoto::store($data['photo'], 'ushers/'.$usher->id);
        $usher->photos()->create([
            'path' => $path,
            'kind' => $usher->photos()->where('kind', 'profile')->exists() ? 'additional' : 'profile',
        ]);
        $usher->load('photos');

        return response()->json($usher->present());
    }

    public function destroyPhoto(Usher $usher, UsherPhoto $photo): Response
    {
        abort_unless($photo->usher_id === $usher->id, 404);
        StoredPhoto::delete($photo->path);
        $photo->delete();

        return response()->noContent();
    }

    public function destroy(Usher $usher): Response
    {
        $usher->load('photos');
        foreach ($usher->photos as $photo) {
            StoredPhoto::delete($photo->path);
        }
        $usher->delete();

        return response()->noContent();
    }

    public function update(Request $request, Usher $usher): JsonResponse
    {
        $adultBy = now()->subYears(18)->toDateString();

        $data = $request->validate([
            'status' => ['sometimes', 'in:Active,Correction Required,Pending'],
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:50'],
            'dob' => ['sometimes', 'nullable', 'date', 'before_or_equal:'.$adultBy],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telegram' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergencyContactName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergencyContactRelationship' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergencyContactPhone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'educationLevel' => ['sometimes', 'nullable', 'string', 'max:100'],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fieldOfStudy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employmentStatus' => ['sometimes', 'nullable', 'string', 'max:100'],
            'experience' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'events' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'availability' => ['sometimes', 'nullable', 'string', 'max:100'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:80'],
            'languages' => ['sometimes', 'nullable', 'array'],
            'languages.*' => ['string', 'max:50'],
            'otherLanguages' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferredEvents' => ['sometimes', 'nullable', 'array'],
            'preferredEvents.*' => ['string', 'max:80'],
            'tshirtSize' => ['sometimes', 'nullable', 'string', 'max:10'],
            'shirtSize' => ['sometimes', 'nullable', 'string', 'max:10'],
            'trouserSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'shoeSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'accountHolder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'accountNumber' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^1000\d+$/'],
            'telebirrNumber' => ['sometimes', 'nullable', 'string', 'max:50'],
            'idType' => ['sometimes', 'nullable', 'string', 'max:100'],
            'idNumber' => ['sometimes', 'nullable', 'string', 'max:100'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook' => ['sometimes', 'nullable', 'string', 'max:255'],
            'linkedin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tiktok' => ['sometimes', 'nullable', 'string', 'max:255'],
            'available' => ['sometimes', 'boolean'],
            'correctionSections' => ['sometimes', 'array', 'min:1'],
            'correctionSections.*' => ['string', 'in:'.implode(',', Usher::CORRECTION_SECTIONS)],
            'correctionNote' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], [
            'dob.before_or_equal' => 'Ushers must be 18 or older.',
            'accountNumber.regex' => 'CBE account numbers must start with 1000 and contain digits only.',
            'correctionSections.min' => 'Select at least one section for the usher to correct.',
        ]);

        $updates = [];

        if (array_key_exists('status', $data)) {
            $updates['status'] = $data['status'];
            if ($data['status'] === 'Correction Required') {
                if (! $usher->profile_edit_token) {
                    $updates['profile_edit_token'] = Str::random(40);
                }
                if (array_key_exists('correctionSections', $data)) {
                    $updates['correction_sections'] = array_values(array_unique($data['correctionSections']));
                } elseif (! $usher->correction_sections) {
                    $updates['correction_sections'] = Usher::CORRECTION_SECTIONS;
                }
                if (array_key_exists('correctionNote', $data)) {
                    $updates['correction_note'] = $data['correctionNote'];
                }
            }
            if (in_array($data['status'], ['Active', 'Pending'], true)) {
                $updates['correction_sections'] = null;
                $updates['correction_note'] = null;
            }
        } elseif (array_key_exists('correctionSections', $data) || array_key_exists('correctionNote', $data)) {
            if (array_key_exists('correctionSections', $data)) {
                $updates['correction_sections'] = array_values(array_unique($data['correctionSections']));
            }
            if (array_key_exists('correctionNote', $data)) {
                $updates['correction_note'] = $data['correctionNote'];
            }
        }

        $map = [
            'name' => 'name',
            'phone' => 'phone',
            'email' => 'email',
            'gender' => 'gender',
            'dob' => 'date_of_birth',
            'city' => 'city',
            'address' => 'address',
            'telegram' => 'telegram',
            'emergencyContactName' => 'emergency_contact_name',
            'emergencyContactRelationship' => 'emergency_contact_relationship',
            'emergencyContactPhone' => 'emergency_contact_phone',
            'educationLevel' => 'education_level',
            'institution' => 'institution',
            'fieldOfStudy' => 'field_of_study',
            'occupation' => 'occupation',
            'employer' => 'employer',
            'employmentStatus' => 'employment_status',
            'experience' => 'years_experience',
            'events' => 'events_count',
            'availability' => 'availability_preference',
            'skills' => 'skills',
            'languages' => 'languages',
            'otherLanguages' => 'other_languages',
            'preferredEvents' => 'preferred_event_types',
            'tshirtSize' => 'tshirt_size',
            'shirtSize' => 'shirt_size',
            'trouserSize' => 'trouser_size',
            'shoeSize' => 'shoe_size',
            'accountHolder' => 'account_holder',
            'accountNumber' => 'account_number',
            'telebirrNumber' => 'telebirr_number',
            'idType' => 'id_type',
            'idNumber' => 'id_number',
            'instagram' => 'instagram',
            'facebook' => 'facebook',
            'linkedin' => 'linkedin',
            'tiktok' => 'tiktok',
            'available' => 'available',
        ];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input];
            }
        }

        if (array_key_exists('account_number', $updates) || array_key_exists('account_holder', $updates)) {
            $updates['payment_method'] = 'CBE';
            $updates['bank_name'] = 'CBE';
        }

        if ($updates !== []) {
            $usher->update($updates);
        }

        $usher->load(['photos', 'experiences', 'references']);

        return response()->json($usher->present());
    }
}
