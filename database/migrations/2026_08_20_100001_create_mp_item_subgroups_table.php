<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named subgroups catalog items are classified under, each belonging to
     * one Item Group. Global reference list, not tied to a specific venue.
     */
    public function up(): void
    {
        Schema::create('mp_item_subgroups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('group_code', 20);
            $table->foreign('group_code')->references('code')->on('mp_item_groups')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('group_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_item_subgroups');
    }
};
