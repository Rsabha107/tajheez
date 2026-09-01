<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mp_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('space_id')->nullable()->after('venue_id');
            $table->unsignedBigInteger('area_id')->nullable()->after('space_id');
        });

        Schema::table('mp_requests', function (Blueprint $table) {
            $table->foreign('space_id')->references('id')->on('mp_spaces')->onDelete('set null');
            $table->foreign('area_id')->references('id')->on('mp_areas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_requests', function (Blueprint $table) {
            $table->dropForeign(['space_id']);
            $table->dropForeign(['area_id']);
            $table->dropColumn(['space_id', 'area_id']);
        });
    }
};
