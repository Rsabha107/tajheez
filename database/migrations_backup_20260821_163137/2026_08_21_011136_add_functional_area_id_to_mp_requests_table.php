<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_requests', function (Blueprint $table) {
            // functional_areas.id is a signed `int` — must match exactly or MySQL
            // rejects the FK (see 2026_08_21_011135_create_functional_area_user_table).
            $table->integer('functional_area_id')->nullable()->after('venue_id');
        });

        Schema::table('mp_requests', function (Blueprint $table) {
            $table->foreign('functional_area_id')->references('id')->on('functional_areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mp_requests', function (Blueprint $table) {
            $table->dropForeign(['functional_area_id']);
            $table->dropColumn('functional_area_id');
        });
    }
};
