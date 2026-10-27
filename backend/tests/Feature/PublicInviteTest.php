<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PublicInviteController;
use App\Models\Usher;
use App\Models\WorkspaceSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PublicInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_link_404s_when_registration_is_paused(): void
    {
        $this->seed();
        $setting = WorkspaceSetting::query()->firstOrFail();
        $this->getJson('/api/public/invite/'.$setting->registration_token)->assertOk();

        $setting->update(['registration_open' => false]);
        $this->getJson('/api/public/invite/'.$setting->registration_token)->assertNotFound();
        $this->postJson('/api/public/invite/'.$setting->registration_token.'/lookup', [
            'name' => 'Hana Tesfaye',
            'phone' => '+251911800000',
        ])->assertNotFound();
    }

    public function test_lookup_returns_active_when_name_and_phone_match_an_active_usher(): void
    {
        $this->seed();
        $token = $this->inviteToken();
        $this->makeUsher([
            'name' => 'Hana Tesfaye',
            'phone' => '+251911800111',
            'status' => 'Active',
        ]);

        $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => '  hana   tesfaye ',
            'phone' => '0911 800 111',
        ])->assertOk()
            ->assertJsonPath('outcome', 'active')
            ->assertJsonPath('message', PublicInviteController::ACTIVE_MESSAGE)
            ->assertJsonMissingPath('session')
            ->assertJsonMissingPath('profile');
    }

    public function test_lookup_returns_amend_when_name_and_phone_match_a_pending_usher(): void
    {
        $this->seed();
        $token = $this->inviteToken();
        $usher = $this->makeUsher([
            'name' => 'Rahel Tadesse',
            'phone' => '+251911800222',
            'status' => 'Pending',
        ]);

        $response = $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Rahel Tadesse',
            'phone' => '+251911800222',
        ])->assertOk()
            ->assertJsonPath('outcome', 'amend')
            ->assertJsonPath('message', PublicInviteController::AMEND_FOUND_MESSAGE)
            ->assertJsonPath('profile.id', $usher->id)
            ->assertJsonPath('profile.accountNumber', '1000123456789');

        $this->assertNotEmpty($response->json('session'));
    }

    public function test_lookup_returns_amend_for_phone_only_or_name_only_matches(): void
    {
        $this->seed();
        $token = $this->inviteToken();
        $byPhone = $this->makeUsher([
            'name' => 'Phone Owner',
            'phone' => '+251911800333',
            'status' => 'Active',
        ]);
        $byName = $this->makeUsher([
            'name' => 'Name Owner',
            'phone' => '+251911800334',
            'status' => 'Pending',
        ]);

        $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Someone Else',
            'phone' => '0911800333',
        ])->assertOk()
            ->assertJsonPath('outcome', 'amend')
            ->assertJsonPath('profile.id', $byPhone->id)
            ->assertJsonPath('message', PublicInviteController::AMEND_PARTIAL_MESSAGE);

        $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Name Owner',
            'phone' => '+251911800999',
        ])->assertOk()
            ->assertJsonPath('outcome', 'amend')
            ->assertJsonPath('profile.id', $byName->id);

        $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Name Owner',
            'phone' => '+251911800333',
        ])->assertOk()
            ->assertJsonPath('outcome', 'amend')
            ->assertJsonPath('profile.id', $byPhone->id);
    }

    public function test_lookup_returns_create_when_neither_name_nor_phone_matches(): void
    {
        $this->seed();
        $token = $this->inviteToken();

        $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Brand New Usher',
            'phone' => '+251911800444',
        ])->assertOk()
            ->assertJsonPath('outcome', 'create')
            ->assertJsonPath('message', PublicInviteController::CREATE_MESSAGE)
            ->assertJsonMissingPath('profile');
    }

    public function test_invite_create_submits_a_pending_usher(): void
    {
        $this->seed();
        $token = $this->inviteToken();
        $session = $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Brand New Usher',
            'phone' => '+251911800444',
        ])->assertOk()->json('session');

        $this->post('/api/public/invite/'.$token, $this->registrationFields([
            'session' => $session,
            'name' => 'Brand New Usher',
            'phone' => '+251911800444',
        ]))->assertCreated();

        $this->assertDatabaseHas('ushers', [
            'name' => 'Brand New Usher',
            'phone' => '+251911800444',
            'status' => 'Pending',
        ]);
    }

    public function test_invite_create_does_not_duplicate_an_active_phone(): void
    {
        $this->seed();
        $token = $this->inviteToken();
        $this->makeUsher([
            'name' => 'Active Phone',
            'phone' => '+251911800555',
            'status' => 'Active',
        ]);
        $session = $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Brand New Usher',
            'phone' => '+251911800444',
        ])->assertOk()->json('session');

        $this->post('/api/public/invite/'.$token, $this->registrationFields([
            'session' => $session,
            'name' => 'Brand New Usher',
            'phone' => '0911 800 555',
            'acct' => '1000987654321',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', PublicInviteController::ACTIVE_CONTACT_MESSAGE);
    }

    public function test_invite_amend_sends_a_pending_or_correction_profile_back_to_pending(): void
    {
        $this->seed();
        $token = $this->inviteToken();
        $pending = $this->makeUsher([
            'name' => 'Pending Person',
            'phone' => '+251911800666',
            'status' => 'Pending',
            'correction_note' => 'old note',
            'correction_sections' => ['about'],
        ]);
        $session = $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Pending Person',
            'phone' => '+251911800666',
        ])->json('session');

        $this->post('/api/public/invite/'.$token, $this->registrationFields([
            'session' => $session,
            'name' => 'Pending Person Updated',
            'phone' => '+251911800666',
            'address' => 'Kazanchis',
        ]))->assertOk();

        $pending->refresh();
        $this->assertSame('Pending Person Updated', $pending->name);
        $this->assertSame('Pending', $pending->status);
        $this->assertNull($pending->correction_note);
        $this->assertNull($pending->correction_sections);

        $correction = $this->makeUsher([
            'name' => 'Needs Fix',
            'phone' => '+251911800667',
            'status' => 'Correction Required',
            'correction_note' => 'Fix phone',
        ]);
        $session = $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Needs Fix',
            'phone' => '+251911800667',
        ])->json('session');
        $this->post('/api/public/invite/'.$token, $this->registrationFields([
            'session' => $session,
            'name' => 'Needs Fix',
            'phone' => '+251911800667',
            'holder' => 'Needs Fix',
        ]))->assertOk();
        $this->assertSame('Pending', $correction->fresh()->status);
    }

    public function test_invite_amend_keeps_an_active_phone_only_match_editable(): void
    {
        $this->seed();
        $token = $this->inviteToken();
        $usher = $this->makeUsher([
            'name' => 'Active Person',
            'phone' => '+251911800777',
            'status' => 'Active',
        ]);
        $session = $this->postJson('/api/public/invite/'.$token.'/lookup', [
            'name' => 'Wrong Name',
            'phone' => '+251911800777',
        ])->assertJsonPath('outcome', 'amend')->json('session');

        $this->post('/api/public/invite/'.$token, $this->registrationFields([
            'session' => $session,
            'name' => 'Active Person',
            'phone' => '+251911800777',
            'holder' => 'Active Person',
        ]))->assertOk();

        $this->assertSame('Pending', $usher->fresh()->status);
    }

    private function inviteToken(): string
    {
        return WorkspaceSetting::query()->firstOrFail()->registration_token;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUsher(array $attributes): Usher
    {
        return Usher::query()->create(array_merge([
            'gender' => 'Female',
            'date_of_birth' => '2000-01-01',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'emergency_contact_name' => 'Sara Bekele',
            'emergency_contact_relationship' => 'Sister',
            'emergency_contact_phone' => '+251911000001',
            'payment_method' => 'CBE',
            'bank_name' => 'CBE',
            'account_holder' => $attributes['name'] ?? 'Invite Usher',
            'account_number' => '1000123456789',
            'id_type' => 'National ID (Fayda)',
            'status' => 'Pending',
            'available' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationFields(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Invite New Person',
            'phone' => '+251911800001',
            'gender' => 'Female',
            'dob' => '2000-01-01',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'ecName' => 'Sara Bekele',
            'ecRel' => 'Sister',
            'ecPhone' => '+251911000001',
            'bank' => 'CBE',
            'holder' => 'Invite New Person',
            'acct' => '1000123456789',
            'idType' => 'National ID (Fayda)',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
        ], $overrides);
    }
}
