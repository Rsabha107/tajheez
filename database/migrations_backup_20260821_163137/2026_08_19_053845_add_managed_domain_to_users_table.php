<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * managed_domain holds the material-planning domain id a "domain-manager"
     * role user owns (IT, LOG, PWR, OVR, FFE) — null for everyone else.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('managed_domain')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('managed_domain');
        });
    }
};
