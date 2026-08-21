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
        Schema::create('mp_service_options', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 40)->unique();
            $table->unsignedBigInteger('catalog_item_id');
            $table->unsignedBigInteger('supplier_id')->index('mp_service_options_supplier_id_foreign');
            $table->string('name', 180);
            $table->decimal('cost', 12);
            $table->unsignedInteger('lead_days')->default(0);
            $table->string('sla', 120);
            $table->unsignedInteger('capacity')->default(0);
            $table->string('contract_reference', 60)->nullable();
            $table->text('spec')->nullable();
            $table->unsignedInteger('classification_id')->index();
            $table->integer('status_id')->default(1)->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['catalog_item_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_service_options');
    }
};
