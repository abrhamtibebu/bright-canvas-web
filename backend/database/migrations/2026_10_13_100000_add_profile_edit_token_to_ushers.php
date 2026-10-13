<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ushers', function (Blueprint $table) {
            $table->string('profile_edit_token')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ushers', function (Blueprint $table) {
            $table->dropUnique(['profile_edit_token']);
            $table->dropColumn('profile_edit_token');
        });
    }
};
