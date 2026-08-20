<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('functional_area_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // functional_areas.id is a signed `int` (created outside Laravel's
            // migration conventions) — must match exactly or MySQL rejects the FK.
            $table->integer('functional_area_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('functional_area_id')->references('id')->on('functional_areas')->cascadeOnDelete();
            $table->unique(['user_id', 'functional_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('functional_area_user');
    }
};
