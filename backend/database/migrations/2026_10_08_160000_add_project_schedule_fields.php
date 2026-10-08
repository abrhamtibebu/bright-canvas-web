<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('transport_provided')->default(true);
            $table->boolean('food_provided')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on', 'transport_provided', 'food_provided']);
        });
    }
};
