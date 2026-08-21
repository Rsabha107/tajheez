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
            $table->foreign(['event_id'])->references(['id'])->on('events')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['functional_area_id'])->references(['id'])->on('functional_areas')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['owner_user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['venue_id'])->references(['id'])->on('venues')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_requests', function (Blueprint $table) {
            $table->dropForeign('mp_requests_event_id_foreign');
            $table->dropForeign('mp_requests_functional_area_id_foreign');
            $table->dropForeign('mp_requests_owner_user_id_foreign');
            $table->dropForeign('mp_requests_venue_id_foreign');
        });
    }
};
