<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_suppliers', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 150);
            $table->string('kind', 100);
            $table->string('status', 20)->default('active'); // preferred|active|suspended
            $table->string('msa_reference', 60)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_suppliers');
    }
};
