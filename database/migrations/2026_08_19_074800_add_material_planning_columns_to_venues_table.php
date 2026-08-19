<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive-only: Material Planning needs city + site/capacity count for
     * venues that the existing venues table doesn't carry. short_name is
     * reused as the venue's short "code" (already holds values like 974/DEC/AZC).
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('city', 120)->nullable()->after('short_name');
            $table->unsignedInteger('sites')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['city', 'sites']);
        });
    }
};
