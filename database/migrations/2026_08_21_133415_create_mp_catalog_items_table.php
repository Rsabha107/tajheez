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
        Schema::create('mp_catalog_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku', 20)->unique();
            $table->unsignedBigInteger('domain_id');
            $table->string('group', 60);
            $table->string('sub', 60);
            $table->string('name', 180);
            $table->string('unit', 20);
            $table->decimal('rate', 12);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('baseline')->default(0);
            $table->timestamps();

            $table->index(['domain_id', 'group', 'sub']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_catalog_items');
    }
};
