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
            $table->string('site_name', 120)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_requests', function (Blueprint $table) {
            // Adjust 'nullable(false)' or keep it required if rolled back.
            $table->string('site_name', 120)->nullable(false)->change();
        });
    }
};