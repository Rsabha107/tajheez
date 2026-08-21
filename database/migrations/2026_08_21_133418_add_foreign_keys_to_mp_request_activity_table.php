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
        Schema::table('mp_request_activity', function (Blueprint $table) {
            $table->foreign(['actor_user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['request_id'])->references(['id'])->on('mp_requests')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_request_activity', function (Blueprint $table) {
            $table->dropForeign('mp_request_activity_actor_user_id_foreign');
            $table->dropForeign('mp_request_activity_request_id_foreign');
        });
    }
};
