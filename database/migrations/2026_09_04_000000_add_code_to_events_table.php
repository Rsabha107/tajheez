<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `start_date`/`end_date` already exist on `events` — only a short display
     * code is new. Nullable + unique: existing events keep working with no
     * code set (frontend falls back to a name-derived abbreviation, see
     * Index.vue's eventCode()), and setting one is optional going forward.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
