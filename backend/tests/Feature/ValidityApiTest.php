<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Usher;
use App\Models\User;
use App\Models\WorkspaceSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_list_ushers(): void
    {
        $this->getJson('/api/ushers')->assertUnauthorized();
    }

    public function test_admin_can_log_in_and_list_ushers(): void
    {
        $this->seed();

        $headers = [
            'referer' => 'http://localhost:3000',
            'origin' => 'http://localhost:3000',
        ];

        $this->withHeaders($headers)->get('/sanctum/csrf-cookie')->assertNoContent();
        $this->withHeaders([
            ...$headers,
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/login', [
            'email' => 'admin@validity.test',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('email', 'admin@validity.test');

        $this->withHeaders($headers)->getJson('/api/ushers')->assertOk()->assertJsonCount(10);
    }

    public function test_registration_requires_a_profile_photo(): void
    {
        $this->seed();
        $token = WorkspaceSetting::query()->firstOrFail()->registration_token;

        $this->post('/api/public/register/'.$token, [
            'name' => 'Liya Bekele',
            'phone' => '+251911000000',
            'gender' => 'Female',
            'dob' => '2000-01-01',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'ecName' => 'Sara Bekele',
            'ecRel' => 'Sister',
            'ecPhone' => '+251911000001',
        ])->assertStatus(422);
    }

    public function test_registration_creates_a_pending_usher(): void
    {
        $this->seed();
        $token = WorkspaceSetting::query()->firstOrFail()->registration_token;

        $this->post('/api/public/register/'.$token, [
            'name' => 'Liya Bekele',
            'phone' => '+251911000000',
            'gender' => 'Female',
            'dob' => '2000-01-01',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'ecName' => 'Sara Bekele',
            'ecRel' => 'Sister',
            'ecPhone' => '+251911000001',
            'skills' => ['Registration'],
            'languages' => ['Amharic'],
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'exEvent0' => 'Addis Expo',
            'exClient0' => 'Demo client',
            'exRole0' => 'Registration',
        ])->assertCreated();

        $this->assertDatabaseHas('ushers', [
            'name' => 'Liya Bekele',
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('usher_experiences', [
            'event_name' => 'Addis Expo',
        ]);
    }

    public function test_availability_token_does_not_accept_another_projects_token(): void
    {
        $this->seed();
        $project = Project::query()->where('name', 'Big 5 Construct Ethiopia')->firstOrFail();
        $meron = Usher::query()->where('name', 'Meron Alemayehu')->firstOrFail();

        $this->postJson('/api/public/availability/'.$project->client_token, [
            'usher_id' => $meron->id,
            'response' => 'Confirmed',
        ])->assertNotFound();

        $this->postJson('/api/public/availability/'.$project->availability_token, [
            'usher_id' => $meron->id,
            'response' => 'Confirmed',
        ])->assertOk();

        $this->assertDatabaseHas('assignments', [
            'project_id' => $project->id,
            'usher_id' => $meron->id,
            'response' => 'Confirmed',
        ]);
    }

    public function test_authenticated_admin_can_read_reports(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.test')->firstOrFail());

        $this->getJson('/api/reports')
            ->assertOk()
            ->assertJsonStructure([
                'averagePerformance',
                'attendanceRate',
                'confirmationRate',
                'clientSatisfaction',
            ]);
    }
}
