<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_catalog_items', function (Blueprint $table) {
            $table->string('sku', 20)->primary();
            $table->string('domain_code', 8);
            $table->foreign('domain_code')->references('code')->on('mp_domains')->restrictOnDelete();
            $table->string('group', 60);
            $table->string('sub', 60);
            $table->string('name', 180);
            $table->string('unit', 20);
            $table->decimal('rate', 12, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('baseline')->default(0);
            $table->timestamps();

            $table->index(['domain_code', 'group', 'sub']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_catalog_items');
    }
};
