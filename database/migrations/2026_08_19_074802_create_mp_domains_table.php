<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_domains', function (Blueprint $table) {
            $table->string('code', 8)->primary();
            $table->string('label', 50);
            $table->string('color', 9);
            $table->string('chip', 9);
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_domains');
    }
};
