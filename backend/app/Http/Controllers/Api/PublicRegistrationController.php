<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usher;
use App\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicRegistrationController extends Controller
{
    public function store(Request $request, string $token): JsonResponse
    {
        WorkspaceSetting::query()->where('registration_token', $token)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:Female,Male'],
            'dob' => ['required', 'date'],
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
            'photo1' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'photo2' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'photo3' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'pay' => ['nullable', 'string', 'max:50'],
            'bank' => ['nullable', 'string', 'max:255'],
            'holder' => ['nullable', 'string', 'max:255'],
            'acct' => ['nullable', 'string', 'max:100'],
            'telebirr' => ['nullable', 'string', 'max:50'],
            'idType' => ['nullable', 'string', 'max:100'],
            'idNo' => ['nullable', 'string', 'max:100'],
            'ig' => ['nullable', 'string', 'max:255'],
            'fb' => ['nullable', 'string', 'max:255'],
            'li' => ['nullable', 'string', 'max:255'],
            'tt' => ['nullable', 'string', 'max:255'],
        ]);

        $usher = Usher::query()->create([
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
            'payment_method' => $data['pay'] ?? null,
            'bank_name' => $data['bank'] ?? null,
            'account_holder' => $data['holder'] ?? null,
            'account_number' => $data['acct'] ?? null,
            'telebirr_number' => $data['telebirr'] ?? null,
            'id_type' => $data['idType'] ?? null,
            'id_number' => $data['idNo'] ?? null,
            'instagram' => $data['ig'] ?? null,
            'facebook' => $data['fb'] ?? null,
            'linkedin' => $data['li'] ?? null,
            'tiktok' => $data['tt'] ?? null,
            'status' => 'Pending',
            'available' => true,
        ]);

        foreach (['photo1' => 'profile', 'photo2' => 'additional', 'photo3' => 'additional'] as $field => $kind) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $path = $request->file($field)->store('ushers/'.$usher->id, 'local');
            $usher->photos()->create(['path' => $path, 'kind' => $kind]);
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

        return response()->json(['ok' => true], 201);
    }
}
