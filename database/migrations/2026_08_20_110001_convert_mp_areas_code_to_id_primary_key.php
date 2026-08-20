<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mp_spaces.area_code still FKs to mp_areas.code — drop it first (MySQL
     * refuses to drop a PRIMARY KEY a foreign key still depends on), it's
     * re-established against area_id in the mp_spaces conversion migration.
     */
    public function up(): void
    {
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->dropForeign(['area_code']);
        });

        Schema::table('mp_areas', function (Blueprint $table) {
            $table->dropPrimary(['code']);
            $table->id()->first();
            $table->unique('code');
        });
    }

    /**
     * Runs after the mp_spaces conversion migration has already been rolled
     * back, so mp_spaces.area_code (FK -> mp_areas.code) already exists
     * again at this point.
     */
    public function down(): void
    {
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->dropForeign(['area_code']);
        });

        Schema::table('mp_areas', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('id');
            $table->primary('code');
        });

        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->foreign('area_code')->references('code')->on('mp_areas')->restrictOnDelete();
        });
    }
};
