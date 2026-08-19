<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_service_options', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('sku', 20);
            $table->foreign('sku')->references('sku')->on('mp_catalog_items')->cascadeOnDelete();
            $table->string('supplier_code', 20);
            $table->foreign('supplier_code')->references('code')->on('mp_suppliers')->restrictOnDelete();
            $table->string('name', 180);
            $table->decimal('cost', 12, 2);
            $table->unsignedInteger('lead_days')->default(0);
            $table->string('sla', 120);
            $table->unsignedInteger('capacity')->default(0);
            $table->string('contract_reference', 60)->nullable();
            $table->text('spec')->nullable();
            $table->string('status', 20)->default('active'); // preferred|active|suspended
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['sku', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_service_options');
    }
};
