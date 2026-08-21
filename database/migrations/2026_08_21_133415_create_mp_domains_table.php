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
        Schema::create('mp_domains', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 8)->unique();
            $table->integer('status_id')->default(1)->index();
            $table->string('label', 50);
            $table->string('color', 9);
            $table->string('chip', 9);
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_domains');
    }
};
