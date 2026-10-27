<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\User;
use App\Models\Usher;
use App\Models\UsherPhoto;
use App\Models\WorkspaceSetting;
use App\Support\StoredPhoto;
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
        $this->get('/api/ushers/1/photos/1')->assertUnauthorized();
    }

    public function test_admin_can_log_in_and_list_ushers(): void
    {
        $this->seed();

        $headers = [
            'referer' => 'http://localhost:3000',
            'origin' => 'http://localhost:3000',
        ];

        $login = $this->withHeaders($headers)->postJson('/api/login', [
            'email' => 'admin@validity.et',
            'password' => 'ValidityAdmin@2026',
        ])->assertOk()->assertJsonPath('email', 'admin@validity.et');

        $this->withToken($login->json('token'))->getJson('/api/ushers')->assertOk()->assertJsonCount(10);
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
            'bank' => 'CBE',
            'holder' => 'Liya Bekele',
            'acct' => '1000123456789',
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422)->assertJsonValidationErrors(['photo1']);
    }

    public function test_registration_rejects_ushers_under_18(): void
    {
        $this->seed();
        $token = WorkspaceSetting::query()->firstOrFail()->registration_token;

        $this->post('/api/public/register/'.$token, [
            'name' => 'Young Usher',
            'phone' => '+251911000099',
            'gender' => 'Female',
            'dob' => now()->subYears(17)->toDateString(),
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'ecName' => 'Sara Bekele',
            'ecRel' => 'Sister',
            'ecPhone' => '+251911000001',
            'bank' => 'CBE',
            'holder' => 'Young Usher',
            'acct' => '1000123456789',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422)->assertJsonValidationErrors(['dob']);
    }

    public function test_registration_requires_cbe_account_starting_with_1000(): void
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
            'bank' => 'CBE',
            'holder' => 'Liya Bekele',
            'acct' => '2000123456789',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422)->assertJsonValidationErrors(['acct']);
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
            'bank' => 'CBE',
            'holder' => 'Liya Bekele',
            'acct' => '1000123456789',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
            'exEvent0' => 'Addis Expo',
            'exClient0' => 'Demo client',
            'exRole0' => 'Registration',
        ])->assertCreated();

        $this->assertDatabaseHas('ushers', [
            'name' => 'Liya Bekele',
            'status' => 'Pending',
            'payment_method' => 'CBE',
            'bank_name' => 'CBE',
            'account_number' => '1000123456789',
            'telebirr_number' => null,
            'id_type' => 'National ID (Fayda)',
        ]);
        $usherId = Usher::query()->where('name', 'Liya Bekele')->value('id');
        $this->assertDatabaseHas('usher_photos', [
            'usher_id' => $usherId,
            'kind' => 'id_front',
        ]);
        $this->assertDatabaseHas('usher_photos', [
            'usher_id' => $usherId,
            'kind' => 'id_back',
        ]);
        $this->assertDatabaseHas('usher_experiences', [
            'event_name' => 'Addis Expo',
        ]);
    }

    public function test_registration_rejects_a_duplicate_phone_number(): void
    {
        $this->seed();
        $token = WorkspaceSetting::query()->firstOrFail()->registration_token;
        $payload = [
            'name' => 'Liya Bekele',
            'phone' => '+251911000000',
            'gender' => 'Female',
            'dob' => '2000-01-01',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
            'ecName' => 'Sara Bekele',
            'ecRel' => 'Sister',
            'ecPhone' => '+251911000001',
            'bank' => 'CBE',
            'holder' => 'Liya Bekele',
            'acct' => '1000123456789',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
        ];

        $this->post('/api/public/register/'.$token, $payload)->assertCreated();

        $this->post('/api/public/register/'.$token, [
            ...$payload,
            'name' => 'Liya Duplicate',
            'phone' => '0911 000 000',
            'acct' => '1000987654321',
            'photo1' => UploadedFile::fake()->create('photo2.jpg', 100, 'image/jpeg'),
            'idPhotoFront' => UploadedFile::fake()->create('id-front-2.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back-2.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'This phone number is already registered.');
    }

    public function test_registration_requires_an_id_photo(): void
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
            'bank' => 'CBE',
            'holder' => 'Liya Bekele',
            'acct' => '1000123456789',
            'idType' => 'National ID (Fayda)',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422)->assertJsonValidationErrors(['idPhotoFront', 'idPhotoBack']);
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
            'bank' => 'CBE',
            'holder' => 'Liya Bekele',
            'acct' => '1000123456789',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
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

        $nested = storage_path('app/extra/ushers/'.$usher['id']);
        if (! is_dir($nested)) {
            mkdir($nested, 0777, true);
        }
        $nestedFile = $nested.'/'.basename($photo->path);
        copy($to, $nestedFile);
        unlink($to);
        $this->get('/api/ushers/'.$usher['id'].'/photos/'.$photo->id)->assertOk();

        unlink($nestedFile);
        $hidden = collect($this->getJson('/api/ushers')->assertOk()->json())->firstWhere('name', 'Liya Bekele');
        $this->assertSame([], $hidden['photos']);
        $this->assertGreaterThan(0, $hidden['missingPhotos']);

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
            'bank' => 'CBE',
            'holder' => 'Liya Bekele',
            'acct' => '1000123456789',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
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
            'bank' => 'CBE',
            'holder' => 'Delete Me',
            'acct' => '1000999888777',
            'photo1' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        $registered = Usher::query()->where('name', 'Delete Me')->firstOrFail();
        $photo = UsherPhoto::query()->where('usher_id', $registered->id)->firstOrFail();
        $this->assertNotNull(StoredPhoto::locate($photo->path));

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
            'idType' => 'National ID (Fayda)',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
            'bank' => 'CBE',
            'holder' => 'Hana Bekele',
            'acct' => '1000123456789',
            'telebirr' => '+251911222333',
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
        $this->assertNotEmpty($admin['idPhotoFront']);
        $this->assertNotEmpty($admin['idPhotoBack']);
        $this->assertSame('Dawit Alemu', $admin['references'][0]['name']);
        $this->assertSame('Addis Expo', $admin['experiences'][0]['event']);
        $this->get($admin['idPhotoFront'])->assertOk();
        $this->get($admin['idPhotoBack'])->assertOk();

        $client = collect($this->getJson('/api/public/client/'.$project->client_token)->assertOk()->json('ushers'))
            ->firstWhere('name', 'Hana Bekele');
        $this->assertSame('Bachelor’s degree', $client['educationLevel']);
        $this->assertSame('Host', $client['occupation']);
        $this->assertSame('Weekends', $client['availability']);
        $this->assertSame('M', $client['tshirtSize']);
        foreach (['phone', 'email', 'address', 'telegram', 'emergencyContactName', 'emergencyContactPhone', 'bankName', 'accountNumber', 'telebirrNumber', 'idNumber', 'idPhoto', 'idPhotoFront', 'idPhotoBack', 'references'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $client);
        }
        $idFront = UsherPhoto::query()->where('usher_id', $usher->id)->where('kind', 'id_front')->firstOrFail();
        $idBack = UsherPhoto::query()->where('usher_id', $usher->id)->where('kind', 'id_back')->firstOrFail();
        $this->get('/api/public/photos/'.$project->client_token.'/'.$idFront->id)->assertNotFound();
        $this->get('/api/public/photos/'.$project->client_token.'/'.$idBack->id)->assertNotFound();
    }

    public function test_personal_confirmation_link_confirms_only_that_usher(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $project = Project::query()->where('name', 'Big 5 Construct Ethiopia')->firstOrFail();
        $usher = Usher::query()->where('status', 'Active')->firstOrFail();
        $this->postJson('/api/projects/'.$project->id.'/invitations', [
            'usher_ids' => [$usher->id],
        ])->assertOk();

        $assignment = Assignment::query()
            ->where('project_id', $project->id)
            ->where('usher_id', $usher->id)
            ->firstOrFail();

        $this->assertNotEmpty($assignment->confirmation_token);
        $listed = collect($this->getJson('/api/assignments')->assertOk()->json())->firstWhere('id', $assignment->id);
        $this->assertSame($assignment->confirmation_token, $listed['confirmationToken']);

        $this->getJson('/api/public/confirm/'.$assignment->confirmation_token)
            ->assertOk()
            ->assertJsonPath('usher.name', $usher->name)
            ->assertJsonPath('project.name', $project->name)
            ->assertJsonMissingPath('usher.phone');

        $this->postJson('/api/public/confirm/'.$assignment->confirmation_token, [
            'response' => 'Confirmed',
        ])->assertOk();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'response' => 'Confirmed',
        ]);
        $this->getJson('/api/public/confirm/not-a-real-token')->assertNotFound();
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

    public function test_admin_can_mark_assignment_paid_and_export_csvs(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $project = Project::query()->where('name', 'Big 5 Construct Ethiopia')->firstOrFail();
        $usher = Usher::query()->where('status', 'Active')->firstOrFail();
        $usher->update([
            'payment_method' => 'Bank transfer',
            'bank_name' => 'CBE',
            'account_holder' => $usher->name,
            'account_number' => '1000123456',
        ]);

        $this->postJson('/api/projects/'.$project->id.'/invitations', [
            'usher_ids' => [$usher->id],
        ])->assertOk();

        $assignment = Assignment::query()
            ->where('project_id', $project->id)
            ->where('usher_id', $usher->id)
            ->firstOrFail();

        $this->patchJson('/api/assignments/'.$assignment->id, [
            'response' => 'Confirmed',
        ])->assertOk();

        $payments = $this->getJson('/api/projects/'.$project->id.'/payments')
            ->assertOk()
            ->json();

        $this->assertSame('Unpaid', $payments['rows'][0]['paymentStatus']);
        $this->assertSame('CBE', $payments['rows'][0]['bankName']);

        $this->patchJson('/api/assignments/'.$assignment->id, [
            'payment_status' => 'Paid',
        ])->assertOk()->assertJsonPath('paymentStatus', 'Paid');

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'payment_status' => 'Paid',
        ]);

        $this->get('/api/export/ushers')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get('/api/export/projects')->assertOk();
        $this->get('/api/projects/'.$project->id.'/payments/export')->assertOk();
    }

    public function test_guests_cannot_export_or_view_payments(): void
    {
        $this->seed();
        $project = Project::query()->firstOrFail();

        $this->get('/api/export/ushers')->assertUnauthorized();
        $this->get('/api/export/projects')->assertUnauthorized();
        $this->getJson('/api/projects/'.$project->id.'/payments')->assertUnauthorized();
        $this->get('/api/projects/'.$project->id.'/payments/export')->assertUnauthorized();
    }

    public function test_admin_can_request_correction_and_usher_can_update_via_link(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = Usher::query()->firstOrFail();
        $updated = $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about', 'payment'],
            'correctionNote' => 'Please fix your bank account and phone number.',
        ])->assertOk()->json();

        $this->assertSame('Correction Required', $updated['status']);
        $this->assertNotEmpty($updated['profileEditToken']);
        $this->assertSame(['about', 'payment'], $updated['correctionSections']);
        $this->assertSame('Please fix your bank account and phone number.', $updated['correctionNote']);

        $token = $updated['profileEditToken'];
        $profile = $this->getJson('/api/public/profile/'.$token)->assertOk()->json();
        $this->assertSame($usher->name, $profile['name']);
        $this->assertSame(['about', 'payment'], $profile['correctionSections']);

        $this->post('/api/public/profile/'.$token, [
            'name' => 'Corrected Usher',
            'phone' => '+251911111111',
            'gender' => 'Female',
            'dob' => '1998-05-05',
            'city' => 'Addis Ababa',
            'address' => 'Kazanchis',
            'bank' => 'CBE',
            'holder' => 'Corrected Usher',
            'acct' => '1000987654321',
            'idType' => 'Passport',
            'idPhotoFront' => UploadedFile::fake()->create('id-front.jpg', 100, 'image/jpeg'),
            'idPhotoBack' => UploadedFile::fake()->create('id-back.jpg', 100, 'image/jpeg'),
        ])->assertOk();

        $this->assertDatabaseHas('ushers', [
            'id' => $usher->id,
            'name' => 'Corrected Usher',
            'status' => 'Pending',
            'account_number' => '1000987654321',
            'correction_note' => null,
        ]);
        $this->assertNull(Usher::query()->findOrFail($usher->id)->correction_sections);
    }

    public function test_correction_accepts_the_ushers_own_phone_in_another_format(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = Usher::query()->firstOrFail();
        $usher->update(['phone' => '+251911666555']);
        $token = $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk()->json('profileEditToken');

        $this->post('/api/public/profile/'.$token, [
            'name' => $usher->name,
            'phone' => '0911 666 555',
            'gender' => 'Female',
            'dob' => '1998-05-05',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
        ])->assertOk();

        $this->assertSame('0911 666 555', Usher::query()->findOrFail($usher->id)->phone);
    }

    public function test_correction_rejects_another_ushers_phone_number(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = Usher::query()->firstOrFail();
        $other = Usher::query()->where('id', '!=', $usher->id)->firstOrFail();
        $usher->update(['phone' => '+251911666555']);
        $other->update(['phone' => '+251922000000']);
        $token = $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk()->json('profileEditToken');

        $this->post('/api/public/profile/'.$token, [
            'name' => $usher->name,
            'phone' => '0922000000',
            'gender' => 'Female',
            'dob' => '1998-05-05',
            'city' => 'Addis Ababa',
            'address' => 'Bole',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'This phone number is already registered.');
    }

    public function test_social_only_correction_does_not_require_other_sections(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = Usher::query()->firstOrFail();
        $originalName = $usher->name;
        $originalPhone = $usher->phone;

        $updated = $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['social'],
        ])->assertOk()->json();

        $token = $updated['profileEditToken'];
        $this->assertSame(['social'], $updated['correctionSections']);

        $this->post('/api/public/profile/'.$token, [
            'ig' => 'https://instagram.com/validity.usher',
            'fb' => '',
            'li' => '',
            'tt' => '',
        ])->assertOk();

        $fresh = Usher::query()->findOrFail($usher->id);
        $this->assertSame($originalName, $fresh->name);
        $this->assertSame($originalPhone, $fresh->phone);
        $this->assertSame('https://instagram.com/validity.usher', $fresh->instagram);
        $this->assertNull($fresh->facebook);
        $this->assertSame('Pending', $fresh->status);
        $this->assertNull($fresh->correction_sections);
    }

    public function test_social_only_correction_accepts_instagram_urls_longer_than_255_characters(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = Usher::query()->firstOrFail();
        $token = $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['social'],
        ])->assertOk()->json('profileEditToken');

        $url = 'https://www.instagram.com/p/'.str_repeat('a', 280).'/?igsh='.str_repeat('b', 80);

        $this->post('/api/public/profile/'.$token, [
            'ig' => $url,
            'fb' => '',
            'li' => '',
            'tt' => '',
        ])->assertOk();

        $this->assertSame($url, Usher::query()->findOrFail($usher->id)->instagram);
    }

    public function test_empty_correction_sections_infer_social_from_posted_keys(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = Usher::query()->firstOrFail();
        $originalName = $usher->name;
        $token = $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['social'],
        ])->assertOk()->json('profileEditToken');

        $usher->update(['correction_sections' => []]);

        $this->post('/api/public/profile/'.$token, [
            'ig' => 'https://instagram.com/inferred.social',
            'fb' => '',
            'li' => '',
            'tt' => '',
        ])->assertOk();

        $fresh = Usher::query()->findOrFail($usher->id);
        $this->assertSame($originalName, $fresh->name);
        $this->assertSame('https://instagram.com/inferred.social', $fresh->instagram);
    }

    public function test_admin_can_edit_usher_details(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());

        $usher = Usher::query()->firstOrFail();
        $this->patchJson('/api/ushers/'.$usher->id, [
            'name' => 'Admin Edited',
            'phone' => '+251900000000',
            'city' => 'Adama',
            'accountHolder' => 'Admin Edited',
            'accountNumber' => '1000111222333',
        ])->assertOk()->assertJsonPath('name', 'Admin Edited');

        $this->assertDatabaseHas('ushers', [
            'id' => $usher->id,
            'name' => 'Admin Edited',
            'city' => 'Adama',
            'account_number' => '1000111222333',
        ]);
    }

    public function test_registration_link_can_be_probed_paused_and_rotated(): void
    {
        $this->seed();
        $setting = WorkspaceSetting::query()->firstOrFail();
        $old = $setting->registration_token;

        $this->getJson('/api/public/register/'.$old)->assertOk()->assertJsonPath('ok', true);

        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $this->patchJson('/api/workspace', ['registrationOpen' => false])
            ->assertOk()
            ->assertJsonPath('registrationOpen', false);

        $this->getJson('/api/public/register/'.$old)->assertNotFound();

        $rotated = $this->postJson('/api/workspace/registration-link/rotate')
            ->assertOk()
            ->json();
        $this->assertTrue($rotated['registrationOpen']);
        $this->assertNotSame($old, $rotated['registrationToken']);
        $this->getJson('/api/public/register/'.$old)->assertNotFound();
        $this->getJson('/api/public/register/'.$rotated['registrationToken'])->assertOk();
    }

    public function test_admin_can_rotate_project_public_links(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $project = Project::query()->firstOrFail();
        $old = $project->availability_token;

        $this->postJson('/api/projects/'.$project->id.'/links/availability/rotate')->assertOk();
        $this->assertNotSame($old, $project->fresh()->availability_token);
        $this->getJson('/api/public/availability/'.$old)->assertNotFound();
    }

    public function test_admin_can_revoke_and_rotate_correction_links(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@validity.et')->firstOrFail());
        $usher = Usher::query()->firstOrFail();
        $this->patchJson('/api/ushers/'.$usher->id, [
            'status' => 'Correction Required',
            'correctionSections' => ['about'],
        ])->assertOk();
        $token = $usher->fresh()->profile_edit_token;
        $this->assertNotEmpty($token);
        $this->getJson('/api/public/profile/'.$token)->assertOk();

        $this->postJson('/api/ushers/'.$usher->id.'/correction-link/revoke')->assertOk();
        $this->getJson('/api/public/profile/'.$token)->assertNotFound();

        $new = $this->postJson('/api/ushers/'.$usher->id.'/correction-link/rotate')->assertOk()->json('profileEditToken');
        $this->assertNotSame($token, $new);
        $this->getJson('/api/public/profile/'.$new)->assertOk();
    }

    public function test_schema_migrate_rejects_wrong_key(): void
    {
        $this->get('/api/public/schema-migrate?key=wrong')->assertNotFound();
    }
}
