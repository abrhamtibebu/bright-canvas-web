<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company')->default('Validity Event & Marketing');
            $table->string('workspace')->default('Usher Directory');
            $table->string('timezone')->default('Addis Ababa (UTC+3)');
            $table->string('currency')->default('Ethiopian birr (ETB)');
            $table->string('registration_token')->unique();
            $table->timestamps();
        });

        Schema::create('ushers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('telegram')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('education_level')->nullable();
            $table->string('institution')->nullable();
            $table->string('field_of_study')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->string('employment_status')->nullable();
            $table->unsignedInteger('years_experience')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('status')->default('Pending');
            $table->boolean('available')->default(true);
            $table->string('availability_preference')->nullable();
            $table->json('skills')->nullable();
            $table->json('languages')->nullable();
            $table->json('preferred_event_types')->nullable();
            $table->string('other_languages')->nullable();
            $table->string('tshirt_size')->nullable();
            $table->string('shirt_size')->nullable();
            $table->string('trouser_size')->nullable();
            $table->string('shoe_size')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('account_number')->nullable();
            $table->string('telebirr_number')->nullable();
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('tiktok')->nullable();
            $table->timestamps();
        });

        Schema::create('usher_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usher_id')->constrained()->cascadeOnDelete();
            $table->string('event_name')->nullable();
            $table->string('client')->nullable();
            $table->string('role')->nullable();
            $table->string('event_type')->nullable();
            $table->timestamps();
        });

        Schema::create('usher_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usher_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('organization')->nullable();
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('usher_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usher_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('kind')->default('profile');
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client');
            $table->string('date_label');
            $table->string('location');
            $table->unsignedInteger('required_ushers');
            $table->string('status')->default('Upcoming');
            $table->string('call_time')->default('7:00 AM');
            $table->string('end_time')->default('6:00 PM');
            $table->string('compensation_label')->default('ETB 1,300 / day');
            $table->string('transport_and_lunch')->default('Provided');
            $table->string('dress_code')->default('Black trousers and a white shirt');
            $table->string('availability_token')->unique();
            $table->string('client_token')->unique();
            $table->string('rating_token')->unique();
            $table->timestamps();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('usher_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('Registration');
            $table->string('response')->default('Invited');
            $table->string('attendance')->default('Expected');
            $table->boolean('client_selected')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'usher_id']);
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('usher_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->string('source');
            $table->timestamps();
            $table->unique(['project_id', 'usher_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('usher_photos');
        Schema::dropIfExists('usher_references');
        Schema::dropIfExists('usher_experiences');
        Schema::dropIfExists('ushers');
        Schema::dropIfExists('workspace_settings');
    }
};
