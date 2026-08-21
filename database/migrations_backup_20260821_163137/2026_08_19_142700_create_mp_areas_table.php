<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Functional areas — broad organizational groupings (Sport, Operations,
     * Media, Commercial, Workforce) that Spaces belong to. Global reference
     * list, not tied to a specific venue or event.
     */
    public function up(): void
    {
        Schema::create('mp_areas', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('label', 80);
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_areas');
    }
};
