<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * area_code's FK to mp_areas was already dropped by the mp_areas
     * conversion migration — only the index remains to clean up here.
     */
    public function up(): void
    {
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id')->nullable()->after('area_code');
        });

        DB::statement('UPDATE mp_spaces s JOIN mp_areas a ON s.area_code = a.code SET s.area_id = a.id');

        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->dropIndex(['area_code']);
            $table->dropColumn('area_code');
        });

        DB::statement('ALTER TABLE mp_spaces MODIFY area_id BIGINT UNSIGNED NOT NULL');

        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('mp_areas')->restrictOnDelete();
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropIndex(['area_id']);
            $table->string('area_code', 20)->nullable()->after('code');
        });

        DB::statement('UPDATE mp_spaces s JOIN mp_areas a ON s.area_id = a.id SET s.area_code = a.code');

        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->dropColumn('area_id');
        });

        DB::statement('ALTER TABLE mp_spaces MODIFY area_code VARCHAR(20) NOT NULL');

        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->foreign('area_code')->references('code')->on('mp_areas')->restrictOnDelete();
            $table->index('area_code');
        });
    }
};
