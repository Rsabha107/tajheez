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
        Schema::table('mp_change_orders', function (Blueprint $table) {
            $table->foreign(['raised_by_user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['request_id'])->references(['id'])->on('mp_requests')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_change_orders', function (Blueprint $table) {
            $table->dropForeign('mp_change_orders_raised_by_user_id_foreign');
            $table->dropForeign('mp_change_orders_request_id_foreign');
        });
    }
};
