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
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->foreign(['area_id'])->references(['id'])->on('mp_areas')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['status_id'])->references(['id'])->on('global_statuses')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_spaces', function (Blueprint $table) {
            $table->dropForeign('mp_spaces_area_id_foreign');
            $table->dropForeign('mp_spaces_status_id_foreign');
        });
    }
};
