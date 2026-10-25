<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Usher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TextBeeSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'textbee.api_key' => 'test-key',
            'textbee.device_id' => 'device-1',
            'textbee.base_url' => 'https://api.textbee.dev/api/v1',
            'app.frontend_url' => 'https://ushers.evella.et',
        ]);
    }

    public function test_correction_request_queues_textbee_sms(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $usher = Usher::query()->firstOrFail();
        $usher->update(['phone' => '0911666555']);
        Http::fake([
            'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response([
                'data' => ['success' => true, 'smsBatchId' => 'batch-1', 'recipientCount' => 1],
            ], 200),
        ]);

        $updated = $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk()
            ->assertJsonPath('sms.queued', true)
            ->assertJsonPath('sms.error', null)
            ->json();

        Http::assertSent(function ($request) use ($updated, $usher) {
            return $request->url() === 'https://api.textbee.dev/api/v1/gateway/send-sms'
                && $request->hasHeader('x-api-key', 'test-key')
                && $request['recipients'] === ['+251911666555']
                && $request['deviceId'] === 'device-1'
                && str_contains($request['message'], 'Validity Events: Dear '.$usher->name.', please update your usher profile:')
                && str_contains($request['message'], 'https://ushers.evella.et/profile/'.$updated['profileEditToken']);
        });
    }

    public function test_correction_sms_skips_missing_phone_without_calling_textbee(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $usher = Usher::query()->firstOrFail();
        $usher->update(['phone' => null]);
        Http::fake();

        $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk()
            ->assertJsonPath('sms.queued', false)
            ->assertJsonPath('sms.error', 'No valid phone number.');

        Http::assertNothingSent();
    }

    public function test_correction_sms_is_skipped_when_api_key_is_missing(): void
    {
        $this->seed();
        config(['textbee.api_key' => '']);
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $usher = Usher::query()->firstOrFail();
        $usher->update(['phone' => '+251911666555']);
        Http::fake();

        $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk()
            ->assertJsonPath('sms.queued', false)
            ->assertJsonPath('sms.error', 'SMS is not configured.');

        Http::assertNothingSent();
    }

    public function test_textbee_rejection_does_not_fail_correction_request(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $usher = Usher::query()->firstOrFail();
        $usher->update(['phone' => '+251911666555']);
        Http::fake([
            'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk()
            ->assertJsonPath('status', 'Correction Required')
            ->assertJsonPath('sms.queued', false)
            ->assertJsonPath('sms.error', 'Unauthorized');
    }

    public function test_invite_queues_personalized_availability_sms(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $withPhone = Usher::query()->where('status', 'Active')->firstOrFail();
        $withoutPhone = Usher::query()->where('status', 'Active')->where('id', '!=', $withPhone->id)->firstOrFail();
        $withPhone->update(['phone' => '+251911222333']);
        $withoutPhone->update(['phone' => null]);
        $project = Project::query()->firstOrFail();
        Http::fake([
            'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response([
                'data' => ['success' => true, 'smsBatchId' => 'batch-2', 'recipientCount' => 1],
            ], 200),
        ]);

        $this->postJson('/api/projects/'.$project->id.'/invitations', [
            'usher_ids' => [$withPhone->id, $withoutPhone->id],
        ])->assertOk()
            ->assertJsonPath('prepared', 2)
            ->assertJsonPath('sms.queued', 1)
            ->assertJsonPath('sms.skipped', 1)
            ->assertJsonPath('sms.error', null);

        Http::assertSent(function ($request) use ($project, $withPhone) {
            return $request['recipients'] === ['+251911222333']
                && str_contains($request['message'], 'Validity Events: Dear '.$withPhone->name.', you are shortlisted for '.$project->name)
                && str_contains($request['message'], 'https://ushers.evella.et/respond/'.$project->availability_token);
        });
        Http::assertSentCount(1);
    }

    public function test_resend_endpoints_queue_sms_without_rotating_tokens(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $usher = Usher::query()->where('status', 'Active')->firstOrFail();
        $usher->update(['phone' => '+251911444555']);
        $project = Project::query()->firstOrFail();
        Http::fake([
            'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response([
                'data' => ['success' => true, 'smsBatchId' => 'batch-3'],
            ], 200),
        ]);

        $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk();
        $token = $usher->fresh()->profile_edit_token;
        $availability = $project->availability_token;

        $this->postJson('/api/projects/'.$project->id.'/invitations', [
            'usher_ids' => [$usher->id],
        ])->assertOk();

        Http::fake([
            'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response([
                'data' => ['success' => true, 'smsBatchId' => 'batch-4'],
            ], 200),
        ]);

        $this->postJson('/api/ushers/'.$usher->id.'/correction-link/sms')
            ->assertOk()
            ->assertJsonPath('sms.queued', true)
            ->assertJsonPath('profileEditToken', $token);
        $this->assertSame($token, $usher->fresh()->profile_edit_token);

        $this->postJson('/api/projects/'.$project->id.'/invitations/sms')
            ->assertOk()
            ->assertJsonPath('sms.queued', 1);
        $this->assertSame($availability, $project->fresh()->availability_token);
    }
}
