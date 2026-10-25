<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Usher;
use App\Models\UsherExperience;
use App\Models\UsherPhoto;
use App\Models\UsherReference;
use App\Models\WorkspaceSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ImportLiveApi extends Command
{
    protected $signature = 'db:import-live-api
                            {--url=https://usher.esmusstrading.com : Live API origin}
                            {--email=admin@validity.et : Admin email}
                            {--password= : Admin password}
                            {--fresh : Recreate the local SQLite schema before importing}
                            {--skip-photos : Import records without downloading photo files}';

    protected $description = 'Copy ushers, projects, assignments, and photos from the live Validity API into local SQLite';

    public function handle(): int
    {
        $base = rtrim((string) $this->option('url'), '/');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        if ($password === '') {
            $this->error('Pass --password for the live admin account.');

            return self::FAILURE;
        }

        $this->info('Live API: '.$base);

        $login = Http::acceptJson()->connectTimeout(30)->timeout(120)->post($base.'/api/login', [
            'email' => $email,
            'password' => $password,
        ]);
        if (! $login->successful() || ! is_string($login->json('token'))) {
            $this->error('Could not sign in to the live API: '.$login->body());

            return self::FAILURE;
        }

        $http = Http::acceptJson()
            ->connectTimeout(30)
            ->timeout(180)
            ->retry(3, 2000)
            ->withToken($login->json('token'))
            ->baseUrl($base);

        $workspace = $http->get('/api/workspace');
        $ushers = $http->get('/api/ushers');
        $projects = $http->get('/api/projects');
        $assignments = $http->get('/api/assignments');

        foreach (['workspace' => $workspace, 'ushers' => $ushers, 'projects' => $projects, 'assignments' => $assignments] as $name => $response) {
            if (! $response->successful()) {
                $this->error("Failed to fetch {$name}: HTTP ".$response->status());

                return self::FAILURE;
            }
        }

        $usherRows = $ushers->json();
        $projectRows = $projects->json();
        $assignmentRows = $assignments->json();
        if (! is_array($usherRows) || ! is_array($projectRows) || ! is_array($assignmentRows)) {
            $this->error('Live API returned an unexpected payload.');

            return self::FAILURE;
        }

        $path = database_path('database.sqlite');
        if ($this->option('fresh')) {
            if (file_exists($path) && filesize($path) > 0) {
                $backup = $path.'.bak-'.date('Ymd-His');
                copy($path, $backup);
                $this->warn('Backed up existing SQLite to '.$backup);
            }
            @unlink($path);
            touch($path);
            DB::purge('sqlite');
            $this->call('migrate', ['--force' => true]);
        }

        Schema::disableForeignKeyConstraints();

        $token = (string) $login->json('token');
        $this->importWorkspace($workspace->json());
        $this->importProjects($projectRows);
        $this->importUshers($usherRows);
        $photos = $this->option('skip-photos') ? 0 : $this->importPhotos($token, $base, $usherRows);
        if ($this->option('skip-photos')) {
            $this->warn('Skipped photo files (--skip-photos).');
        }
        $this->importAssignments($assignmentRows);
        $this->importUsersFromPgsql();
        $this->importProjectsFromPgsqlIfEmpty();

        Schema::enableForeignKeyConstraints();
        $this->alignSequences();

        $this->info(sprintf(
            'Imported %d ushers, %d projects, %d assignments, %d photos into %s',
            count($usherRows),
            count($projectRows),
            count($assignmentRows),
            $photos,
            $path,
        ));

        return self::SUCCESS;
    }

    private function importWorkspace(mixed $payload): void
    {
        if (! is_array($payload)) {
            return;
        }
        WorkspaceSetting::query()->delete();
        WorkspaceSetting::query()->create([
            'company' => $payload['company'] ?? 'Validity Events',
            'workspace' => $payload['workspace'] ?? 'Usher Directory',
            'timezone' => $payload['timezone'] ?? 'Addis Ababa (UTC+3)',
            'currency' => $payload['currency'] ?? 'Ethiopian birr (ETB)',
            'registration_token' => $payload['registrationToken'] ?? null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importProjects(array $rows): void
    {
        Assignment::query()->delete();
        Project::query()->delete();
        foreach ($rows as $row) {
            $project = new Project;
            $project->id = $row['id'];
            $project->forceFill([
                'name' => $row['name'],
                'client' => $row['client'],
                'date_label' => $row['date'] ?? '',
                'location' => $row['location'] ?? '',
                'required_ushers' => $row['required'] ?? 0,
                'status' => $row['status'] ?? 'Upcoming',
                'call_time' => $row['callTime'] ?? '7:00 AM',
                'end_time' => $row['endTime'] ?? '6:00 PM',
                'compensation_label' => $row['compensation'] ?? 'ETB 1,300 / day',
                'transport_and_lunch' => $row['transport'] ?? 'Provided',
                'dress_code' => $row['dressCode'] ?? 'Black trousers and a white shirt',
                'starts_on' => $row['startsOn'] ?? null,
                'ends_on' => $row['endsOn'] ?? null,
                'transport_provided' => $row['transportProvided'] ?? true,
                'food_provided' => $row['foodProvided'] ?? true,
                'availability_token' => $row['availabilityToken'] ?? null,
                'client_token' => $row['clientToken'] ?? null,
                'rating_token' => $row['ratingToken'] ?? null,
            ])->save();
        }
        $this->line(sprintf('%-28s %d rows', 'projects', count($rows)));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importUshers(array $rows): void
    {
        UsherPhoto::query()->delete();
        UsherExperience::query()->delete();
        UsherReference::query()->delete();
        Usher::query()->delete();

        foreach ($rows as $row) {
            $usher = new Usher;
            $usher->id = $row['id'];
            $usher->forceFill([
                'name' => $row['name'],
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'gender' => $row['gender'] ?? null,
                'date_of_birth' => $row['dob'] ?? null,
                'city' => $row['city'] ?? null,
                'address' => $row['address'] ?? null,
                'telegram' => $row['telegram'] ?? null,
                'emergency_contact_name' => $row['emergencyContactName'] ?? null,
                'emergency_contact_relationship' => $row['emergencyContactRelationship'] ?? null,
                'emergency_contact_phone' => $row['emergencyContactPhone'] ?? null,
                'education_level' => $row['educationLevel'] ?? null,
                'institution' => $row['institution'] ?? null,
                'field_of_study' => $row['fieldOfStudy'] ?? null,
                'occupation' => $row['occupation'] ?? null,
                'employer' => $row['employer'] ?? null,
                'employment_status' => $row['employmentStatus'] ?? null,
                'years_experience' => $row['experience'] ?? 0,
                'events_count' => $row['events'] ?? 0,
                'rating' => $row['rating'] ?? 0,
                'status' => $row['status'] ?? 'Pending',
                'available' => $row['available'] ?? true,
                'availability_preference' => $row['availability'] ?? null,
                'skills' => $row['skills'] ?? [],
                'languages' => $row['languages'] ?? [],
                'preferred_event_types' => $row['preferredEvents'] ?? [],
                'other_languages' => $row['otherLanguages'] ?? null,
                'tshirt_size' => $row['tshirtSize'] ?? null,
                'shirt_size' => $row['shirtSize'] ?? null,
                'trouser_size' => $row['trouserSize'] ?? null,
                'shoe_size' => $row['shoeSize'] ?? null,
                'payment_method' => $row['paymentMethod'] ?? null,
                'bank_name' => $row['bankName'] ?? null,
                'account_holder' => $row['accountHolder'] ?? null,
                'account_number' => $row['accountNumber'] ?? null,
                'telebirr_number' => $row['telebirrNumber'] ?? null,
                'id_type' => $row['idType'] ?? null,
                'id_number' => $row['idNumber'] ?? null,
                'instagram' => $row['instagram'] ?? null,
                'facebook' => $row['facebook'] ?? null,
                'linkedin' => $row['linkedin'] ?? null,
                'tiktok' => $row['tiktok'] ?? null,
                'profile_edit_token' => $row['profileEditToken'] ?? null,
                'correction_sections' => $row['correctionSections'] ?? null,
                'correction_note' => $row['correctionNote'] ?? null,
            ])->save();

            foreach ($row['experiences'] ?? [] as $experience) {
                $usher->experiences()->create([
                    'event_name' => $experience['event'] ?? null,
                    'client' => $experience['client'] ?? null,
                    'role' => $experience['role'] ?? null,
                    'event_type' => $experience['eventType'] ?? null,
                ]);
            }
            foreach ($row['references'] ?? [] as $reference) {
                $usher->references()->create([
                    'name' => $reference['name'] ?? null,
                    'organization' => $reference['organization'] ?? null,
                    'relationship' => $reference['relationship'] ?? null,
                    'phone' => $reference['phone'] ?? null,
                    'email' => $reference['email'] ?? null,
                    'notes' => $reference['notes'] ?? null,
                ]);
            }
        }

        $this->line(sprintf('%-28s %d rows', 'ushers', count($rows)));
    }

    /**
     * @param  list<array<string, mixed>>  $ushers
     */
    private function importPhotos(string $token, string $base, array $ushers): int
    {
        $count = 0;
        foreach ($ushers as $usher) {
            $id = (int) $usher['id'];
            $photos = [];
            foreach ($usher['photos'] ?? [] as $index => $url) {
                $photoId = $this->photoId($url);
                if ($photoId) {
                    $photos[$photoId] = $index === 0 ? 'profile' : 'additional';
                }
            }
            foreach (['idPhotoFront' => 'id_front', 'idPhoto' => 'id_front', 'idPhotoBack' => 'id_back'] as $field => $kind) {
                $photoId = $this->photoId($usher[$field] ?? null);
                if ($photoId) {
                    $photos[$photoId] = $kind;
                }
            }

            foreach ($photos as $photoId => $kind) {
                $path = $this->downloadPhoto($token, $base, $id, $photoId);
                if ($path === null) {
                    continue;
                }
                $photo = new UsherPhoto;
                $photo->id = $photoId;
                $photo->forceFill([
                    'usher_id' => $id,
                    'path' => $path,
                    'kind' => $kind,
                ])->save();
                $count++;
            }
        }

        $this->line(sprintf('%-28s %d rows', 'usher_photos', $count));

        return $count;
    }

    private function downloadPhoto(string $token, string $base, int $usherId, int $photoId): ?string
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(60)
                ->connectTimeout(20)
                ->retry(2, 1000, when: fn ($exception) => $exception instanceof \Illuminate\Http\Client\ConnectionException, throw: false)
                ->get("{$base}/api/ushers/{$usherId}/photos/{$photoId}");
        } catch (\Throwable $e) {
            $this->warn("Missing photo usher={$usherId} photo={$photoId}: ".$e->getMessage());

            return null;
        }
        if (! $response->successful() || $response->body() === '') {
            $this->warn("Missing photo usher={$usherId} photo={$photoId} HTTP ".$response->status());

            return null;
        }

        $mime = strtolower((string) $response->header('Content-Type'));
        $ext = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'webp') ? 'webp' : 'jpg');
        $path = "ushers/{$usherId}/live-{$photoId}.{$ext}";
        Storage::disk('local')->put($path, $response->body());

        return $path;
    }

    private function photoId(mixed $url): ?int
    {
        if (! is_string($url) || ! preg_match('#/photos/(\d+)#', $url, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importAssignments(array $rows): void
    {
        Assignment::query()->delete();
        foreach ($rows as $row) {
            $assignment = new Assignment;
            $assignment->id = $row['id'];
            $assignment->forceFill([
                'project_id' => $row['projectId'],
                'usher_id' => $row['usherId'],
                'role' => $row['role'] ?? 'Registration',
                'response' => $row['response'] ?? 'Invited',
                'attendance' => $row['attendance'] ?? 'Expected',
                'client_selected' => $row['clientSelected'] ?? false,
                'payment_status' => $row['paymentStatus'] ?? 'Unpaid',
                'confirmation_token' => $row['confirmationToken'] ?? null,
            ])->save();
        }
        $this->line(sprintf('%-28s %d rows', 'assignments', count($rows)));
    }

    private function importUsersFromPgsql(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            return;
        }
        try {
            $rows = DB::connection('pgsql')->table('users')->get();
        } catch (\Throwable) {
            return;
        }
        if ($rows->isEmpty()) {
            return;
        }
        DB::table('users')->delete();
        foreach ($rows as $row) {
            DB::table('users')->insert((array) $row);
        }
        $this->line(sprintf('%-28s %d rows', 'users (pgsql hashes)', $rows->count()));
    }

    private function importProjectsFromPgsqlIfEmpty(): void
    {
        if (Project::query()->exists() || ! extension_loaded('pdo_pgsql')) {
            return;
        }
        try {
            $projects = DB::connection('pgsql')->table('projects')->get();
            $assignments = DB::connection('pgsql')->table('assignments')->get();
            $ratings = Schema::connection('pgsql')->hasTable('ratings')
                ? DB::connection('pgsql')->table('ratings')->get()
                : collect();
        } catch (\Throwable) {
            return;
        }
        if ($projects->isEmpty()) {
            return;
        }
        foreach ($projects as $row) {
            DB::table('projects')->insert((array) $row);
        }
        foreach ($assignments as $row) {
            $data = (array) $row;
            if (! Usher::query()->whereKey($data['usher_id'] ?? 0)->exists()) {
                continue;
            }
            DB::table('assignments')->insert($data);
        }
        if (Schema::hasTable('ratings')) {
            foreach ($ratings as $row) {
                $data = (array) $row;
                if (! Usher::query()->whereKey($data['usher_id'] ?? 0)->exists()) {
                    continue;
                }
                DB::table('ratings')->insert($data);
            }
        }
        $this->line(sprintf('%-28s %d rows (from pgsql, live API had none)', 'projects', $projects->count()));
        $this->line(sprintf('%-28s %d rows (from pgsql)', 'assignments', $assignments->count()));
    }

    private function alignSequences(): void
    {
        foreach (['users', 'ushers', 'projects', 'assignments', 'usher_photos', 'usher_experiences', 'usher_references', 'workspace_settings'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }
            $max = (int) DB::table($table)->max('id');
            if ($max <= 0) {
                continue;
            }
            DB::table('sqlite_sequence')->where('name', $table)->delete();
            DB::table('sqlite_sequence')->insert(['name' => $table, 'seq' => $max]);
        }
    }
}
