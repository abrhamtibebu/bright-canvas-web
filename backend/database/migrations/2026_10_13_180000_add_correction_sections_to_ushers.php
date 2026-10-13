<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ushers', function (Blueprint $table) {
            $table->json('correction_sections')->nullable()->after('profile_edit_token');
            $table->text('correction_note')->nullable()->after('correction_sections');
        });
    }

    public function down(): void
    {
        Schema::table('ushers', function (Blueprint $table) {
            $table->dropColumn(['correction_sections', 'correction_note']);
        });
    }
};
