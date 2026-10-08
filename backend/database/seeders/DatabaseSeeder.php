<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Usher;
use App\Models\User;
use App\Models\WorkspaceSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->whereIn('email', ['admin@validity.et', 'admin@test.com'])->first()
            ?? new User();
        $admin->fill([
            'name' => 'Validity Admin',
            'email' => 'admin@validity.et',
            'password' => 'ValidityAdmin@2026',
        ]);
        $admin->save();

        WorkspaceSetting::query()->firstOrCreate([], [
            'company' => 'Validity Events',
            'workspace' => 'Usher Directory',
            'timezone' => 'Addis Ababa (UTC+3)',
            'currency' => 'Ethiopian birr (ETB)',
        ]);

        $ushers = [
            ['name' => 'Hana Tesfaye', 'city' => 'Addis Ababa', 'gender' => 'Female', 'years_experience' => 3, 'events_count' => 24, 'rating' => 4.9, 'skills' => ['Guest Relations', 'VIP Handling'], 'languages' => ['Amharic', 'English'], 'status' => 'Active', 'available' => true],
            ['name' => 'Abebe Kebede', 'city' => 'Addis Ababa', 'gender' => 'Male', 'years_experience' => 4, 'events_count' => 32, 'rating' => 4.8, 'skills' => ['Registration', 'Team Leader'], 'languages' => ['Amharic', 'English'], 'status' => 'Active', 'available' => true],
            ['name' => 'Meron Alemayehu', 'city' => 'Addis Ababa', 'gender' => 'Female', 'years_experience' => 2, 'events_count' => 18, 'rating' => 4.7, 'skills' => ['Hospitality', 'Registration'], 'languages' => ['Amharic', 'English'], 'status' => 'Active', 'available' => true],
            ['name' => 'Dawit Bekele', 'city' => 'Adama', 'gender' => 'Male', 'years_experience' => 3, 'events_count' => 21, 'rating' => 4.8, 'skills' => ['Crowd Management', 'Ticketing'], 'languages' => ['Amharic', 'Afaan Oromo'], 'status' => 'Active', 'available' => false],
            ['name' => 'Selam Getachew', 'city' => 'Addis Ababa', 'gender' => 'Female', 'years_experience' => 2, 'events_count' => 15, 'rating' => 4.6, 'skills' => ['Brand Ambassador', 'Guest Relations'], 'languages' => ['Amharic', 'English'], 'status' => 'Active', 'available' => true],
            ['name' => 'Rahel Tadesse', 'city' => 'Addis Ababa', 'gender' => 'Female', 'years_experience' => 1, 'events_count' => 6, 'rating' => 4.5, 'skills' => ['Registration'], 'languages' => ['Amharic', 'English'], 'status' => 'Pending', 'available' => true],
            ['name' => 'Yonas Girma', 'city' => 'Addis Ababa', 'gender' => 'Male', 'years_experience' => 2, 'events_count' => 11, 'rating' => 4.6, 'skills' => ['Ticketing', 'Guest Relations'], 'languages' => ['Amharic', 'English'], 'status' => 'Pending', 'available' => true],
            ['name' => 'Bethlehem Assefa', 'city' => 'Addis Ababa', 'gender' => 'Female', 'years_experience' => 1, 'events_count' => 4, 'rating' => 4.4, 'skills' => ['Hospitality'], 'languages' => ['Amharic', 'English'], 'status' => 'Pending', 'available' => true],
            ['name' => 'Nahom Solomon', 'city' => 'Adama', 'gender' => 'Male', 'years_experience' => 2, 'events_count' => 14, 'rating' => 4.7, 'skills' => ['Registration', 'VIP Handling'], 'languages' => ['Amharic', 'English'], 'status' => 'Active', 'available' => true],
            ['name' => 'Tigist Mekonnen', 'city' => 'Addis Ababa', 'gender' => 'Female', 'years_experience' => 4, 'events_count' => 29, 'rating' => 4.9, 'skills' => ['VIP Handling', 'Team Leader'], 'languages' => ['Amharic', 'English'], 'status' => 'Active', 'available' => true],
        ];

        foreach ($ushers as $usher) {
            Usher::query()->updateOrCreate(['name' => $usher['name']], $usher);
        }

        $projects = [
            ['name' => 'Big 5 Construct Ethiopia', 'client' => 'dmg events', 'date_label' => 'Nov 20 – 23, 2026', 'location' => 'Addis International Convention Center', 'required_ushers' => 20, 'status' => 'Upcoming'],
            ['name' => 'Telebirr Anniversary', 'client' => 'Ethio telecom', 'date_label' => 'Nov 28, 2026', 'location' => 'Millennium Hall, Addis Ababa', 'required_ushers' => 16, 'status' => 'Upcoming'],
            ['name' => 'Corporate Leadership Forum', 'client' => 'Demo client', 'date_label' => 'Oct 15, 2026', 'location' => 'Sheraton Addis', 'required_ushers' => 8, 'status' => 'Active'],
        ];

        foreach ($projects as $project) {
            Project::query()->updateOrCreate(['name' => $project['name']], $project);
        }

        $big5 = Project::query()->where('name', 'Big 5 Construct Ethiopia')->firstOrFail();
        $responses = [
            'Hana Tesfaye' => 'Confirmed',
            'Abebe Kebede' => 'Confirmed',
            'Meron Alemayehu' => 'Invited',
        ];

        foreach ($responses as $name => $response) {
            Assignment::query()->updateOrCreate(
                [
                    'project_id' => $big5->id,
                    'usher_id' => Usher::query()->where('name', $name)->firstOrFail()->id,
                ],
                [
                    'role' => 'Registration',
                    'response' => $response,
                    'attendance' => 'Expected',
                ],
            );
        }
    }
}
