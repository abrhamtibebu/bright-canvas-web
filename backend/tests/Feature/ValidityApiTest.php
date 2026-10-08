<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Usher;
use App\Models\UsherPhoto;
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
            'email' => 'admin@validity.et',
            'password' => 'ValidityAdmin@2026',
        ])->assertOk()->assertJsonPath('email', 'admin@validity.et');

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

    public function test_admin_can_view_a_registration_photo(): void
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
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = collect($this->getJson('/api/ushers')->assertOk()->json())
            ->firstWhere('name', 'Liya Bekele');

        $this->assertNotEmpty($usher['photos']);
        $this->get($usher['photos'][0])->assertOk();

        $photo = UsherPhoto::query()->where('usher_id', $usher['id'])->firstOrFail();
        $from = storage_path('app/private/'.$photo->path);
        $to = storage_path('app/'.$photo->path);
        if (! is_dir(dirname($to))) {
            mkdir(dirname($to), 0777, true);
        }
        rename($from, $to);
        $this->get('/api/ushers/'.$usher['id'].'/photos/'.$photo->id)->assertOk();

        unlink($to);
        $hidden = collect($this->getJson('/api/ushers')->assertOk()->json())->firstWhere('name', 'Liya Bekele');
        $this->assertSame([], $hidden['photos']);

        $replaced = $this->post('/api/ushers/'.$usher['id'].'/photos', [
            'photo' => UploadedFile::fake()->create('again.jpg', 80, 'image/jpeg'),
        ])->assertOk()->json();
        $this->assertNotEmpty($replaced['photos']);
        $this->get($replaced['photos'][0])->assertOk();
    }

    public function test_client_can_view_confirmed_usher_photos(): void
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
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        $usher = Usher::query()->where('name', 'Liya Bekele')->firstOrFail();
        $project = Project::query()->where('name', 'Big 5 Construct Ethiopia')->firstOrFail();
        Assignment::query()->updateOrCreate(
            ['project_id' => $project->id, 'usher_id' => $usher->id],
            ['role' => 'Registration', 'response' => 'Confirmed', 'attendance' => 'Expected'],
        );

        $row = collect($this->getJson('/api/public/client/'.$project->client_token)->assertOk()->json('ushers'))
            ->firstWhere('name', 'Liya Bekele');

        $this->assertNotEmpty($row['photos']);
        $this->get($row['photos'][0])->assertOk();

        $photo = UsherPhoto::query()->where('usher_id', $usher->id)->firstOrFail();
        $this->get('/api/public/photos/'.$project->availability_token.'/'.$photo->id)->assertNotFound();
        $this->getJson('/api/public/ratings/'.$project->rating_token)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Liya Bekele']);
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

    public function test_admin_creates_a_multi_day_project_and_can_delete_records(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $this->postJson('/api/projects', [
            'name' => 'Summit',
            'client' => 'Acme',
            'starts_on' => '2026-11-03',
            'ends_on' => '2026-11-01',
            'location' => 'Millennium Hall',
            'required' => 12,
            'call_time' => '08:00',
            'end_time' => '17:30',
            'transport_provided' => false,
            'food_provided' => true,
            'compensation' => 'ETB 2,000 / day',
            'dress_code' => 'Navy suit',
        ])->assertStatus(422);

        $this->postJson('/api/projects', [
            'name' => 'Summit',
            'client' => 'Acme',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-03',
            'location' => 'Millennium Hall',
            'required' => 12,
            'call_time' => '08:00',
            'end_time' => '07:00',
            'transport_provided' => true,
            'food_provided' => true,
            'compensation' => 'ETB 2,000 / day',
            'dress_code' => 'Navy suit',
        ])->assertStatus(422)->assertJsonValidationErrors('end_time');

        $created = $this->postJson('/api/projects', [
            'name' => 'Summit',
            'client' => 'Acme',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-03',
            'location' => 'Millennium Hall',
            'required' => 12,
            'call_time' => '08:00',
            'end_time' => '17:30',
            'transport_provided' => false,
            'food_provided' => true,
            'compensation' => 'ETB 2,000 / day',
            'dress_code' => 'Navy suit',
        ])->assertCreated()
            ->assertJsonPath('date', 'Nov 1–3, 2026')
            ->assertJsonPath('callTime', '8:00 AM')
            ->assertJsonPath('endTime', '5:30 PM')
            ->assertJsonPath('transport', 'Food provided, transport not included')
            ->assertJsonPath('transportProvided', false)
            ->assertJsonPath('foodProvided', true)
            ->json();

        $usher = Usher::query()->where('status', 'Active')->firstOrFail();
        $this->postJson('/api/projects/'.$created['id'].'/invitations', [
            'usher_ids' => [$usher->id],
        ])->assertOk();

        $assignment = Assignment::query()->where('project_id', $created['id'])->firstOrFail();
        $this->deleteJson('/api/assignments/'.$assignment->id)->assertNoContent();
        $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);

        $this->deleteJson('/api/projects/'.$created['id'])->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $created['id']]);

        $token = WorkspaceSetting::query()->firstOrFail()->registration_token;
        $this->post('/api/public/register/'.$token, [
            'name' => 'Delete Me',
            'phone' => '+251911000099',
            'gender' => 'Female',
            'dob' => '2000-01-01',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'ecName' => 'Sara Bekele',
            'ecRel' => 'Sister',
            'ecPhone' => '+251911000098',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        $registered = Usher::query()->where('name', 'Delete Me')->firstOrFail();
        $photo = UsherPhoto::query()->where('usher_id', $registered->id)->firstOrFail();
        $this->assertNotNull(\App\Support\StoredPhoto::locate($photo->path));

        $this->deleteJson('/api/ushers/'.$registered->id.'/photos/'.$photo->id)->assertNoContent();
        $this->assertDatabaseMissing('usher_photos', ['id' => $photo->id]);

        $this->deleteJson('/api/ushers/'.$registered->id)->assertNoContent();
        $this->assertDatabaseMissing('ushers', ['id' => $registered->id]);
    }

    public function test_admin_sees_full_usher_records_and_clients_see_recruiting_details_only(): void
    {
        $this->seed();
        $token = WorkspaceSetting::query()->firstOrFail()->registration_token;

        $this->post('/api/public/register/'.$token, [
            'name' => 'Hana Bekele',
            'phone' => '+251911222333',
            'email' => 'hana@example.com',
            'gender' => 'Female',
            'dob' => '2000-01-01',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'telegram' => '@hana',
            'ecName' => 'Sara Bekele',
            'ecRel' => 'Sister',
            'ecPhone' => '+251911000001',
            'edu' => 'Bachelor’s degree',
            'inst' => 'AAU',
            'field' => 'Marketing',
            'occ' => 'Host',
            'employer' => 'Validity',
            'empStatus' => 'Freelancer',
            'languages' => ['Amharic', 'English'],
            'skills' => ['Registration'],
            'prefs' => ['Conference'],
            'availability' => 'Weekends',
            'tshirt' => 'M',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'pay' => 'Bank transfer',
            'bank' => 'CBE',
            'holder' => 'Hana Bekele',
            'acct' => '1000123456789',
            'telebirr' => '+251911222333',
            'idType' => 'National ID (Fayda)',
            'idNo' => 'ID-9988',
            'exEvent0' => 'Addis Expo',
            'exClient0' => 'Demo client',
            'exRole0' => 'Registration',
            'rName0' => 'Dawit Alemu',
            'rPhone0' => '+251911000777',
            'rEmail0' => 'dawit@example.com',
        ])->assertCreated();

        $usher = Usher::query()->where('name', 'Hana Bekele')->firstOrFail();
        $project = Project::query()->where('name', 'Big 5 Construct Ethiopia')->firstOrFail();
        Assignment::query()->updateOrCreate(
            ['project_id' => $project->id, 'usher_id' => $usher->id],
            ['role' => 'Registration', 'response' => 'Confirmed', 'attendance' => 'Expected'],
        );

        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $admin = collect($this->getJson('/api/ushers')->assertOk()->json())->firstWhere('name', 'Hana Bekele');
        $this->assertSame('+251911222333', $admin['phone']);
        $this->assertSame('hana@example.com', $admin['email']);
        $this->assertSame('Sara Bekele', $admin['emergencyContactName']);
        $this->assertSame('1000123456789', $admin['accountNumber']);
        $this->assertSame('ID-9988', $admin['idNumber']);
        $this->assertSame('Dawit Alemu', $admin['references'][0]['name']);
        $this->assertSame('Addis Expo', $admin['experiences'][0]['event']);

        $client = collect($this->getJson('/api/public/client/'.$project->client_token)->assertOk()->json('ushers'))
            ->firstWhere('name', 'Hana Bekele');
        $this->assertSame('Bachelor’s degree', $client['educationLevel']);
        $this->assertSame('Host', $client['occupation']);
        $this->assertSame('Weekends', $client['availability']);
        $this->assertSame('M', $client['tshirtSize']);
        foreach (['phone', 'email', 'address', 'telegram', 'emergencyContactName', 'emergencyContactPhone', 'bankName', 'accountNumber', 'telebirrNumber', 'idNumber', 'references'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $client);
        }
    }

    public function test_guests_cannot_delete_records(): void
    {
        $this->seed();
        $usher = Usher::query()->firstOrFail();
        $project = Project::query()->firstOrFail();

        $this->deleteJson('/api/ushers/'.$usher->id)->assertUnauthorized();
        $this->deleteJson('/api/projects/'.$project->id)->assertUnauthorized();
    }

    public function test_authenticated_admin_can_read_reports(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

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
