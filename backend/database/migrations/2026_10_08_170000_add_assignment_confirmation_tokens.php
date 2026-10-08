<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('confirmation_token', 40)->nullable()->unique();
        });

        foreach (DB::table('assignments')->whereNull('confirmation_token')->pluck('id') as $id) {
            DB::table('assignments')->where('id', $id)->update([
                'confirmation_token' => Str::random(40),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropUnique(['confirmation_token']);
            $table->dropColumn('confirmation_token');
        });
    }
};
