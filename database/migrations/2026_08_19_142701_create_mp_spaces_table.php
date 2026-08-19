<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named spaces requests get built out in (e.g. "Mixed Zone — North",
     * "Broadcast Compound"), each belonging to one functional Area. Global
     * reference list, not tied to a specific venue.
     */
    public function up(): void
    {
        Schema::create('mp_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('area_code', 20);
            $table->foreign('area_code')->references('code')->on('mp_areas')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('area_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_spaces');
    }
};
