<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_change_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_order_id')->constrained('mp_change_orders')->cascadeOnDelete();
            $table->foreignId('request_line_id')->nullable()->constrained('mp_request_lines')->nullOnDelete();
            $table->string('sku', 20);
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->restrictOnDelete();
            $table->unsignedInteger('qty_before')->nullable();
            $table->unsignedInteger('qty_after')->nullable();
            $table->decimal('rate_before', 12, 2)->nullable();
            $table->decimal('rate_after', 12, 2)->nullable();
            $table->foreignId('service_option_before_id')->nullable()->constrained('mp_service_options')->nullOnDelete();
            $table->foreignId('service_option_after_id')->nullable()->constrained('mp_service_options')->nullOnDelete();
            $table->string('why', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_change_order_lines');
    }
};
