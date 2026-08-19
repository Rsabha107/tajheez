<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('mp_requests')->cascadeOnDelete();
            $table->string('sku', 20);
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('rate_snapshot', 12, 2);
            $table->string('comment', 255)->nullable();
            $table->foreignId('service_option_id')->nullable()->constrained('mp_service_options')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_request_lines');
    }
};
