<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ushers', function (Blueprint $table) {
            $table->string('instagram', 2048)->nullable()->change();
            $table->string('facebook', 2048)->nullable()->change();
            $table->string('linkedin', 2048)->nullable()->change();
            $table->string('tiktok', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ushers', function (Blueprint $table) {
            $table->string('instagram', 255)->nullable()->change();
            $table->string('facebook', 255)->nullable()->change();
            $table->string('linkedin', 255)->nullable()->change();
            $table->string('tiktok', 255)->nullable()->change();
        });
    }
};
