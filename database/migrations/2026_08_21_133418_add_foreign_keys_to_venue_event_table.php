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
        Schema::table('venue_event', function (Blueprint $table) {
            $table->foreign(['event_id'])->references(['id'])->on('events')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['venue_id'])->references(['id'])->on('venues')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venue_event', function (Blueprint $table) {
            $table->dropForeign('venue_event_event_id_foreign');
            $table->dropForeign('venue_event_venue_id_foreign');
        });
    }
};
