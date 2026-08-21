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
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->foreign(['domain_id'])->references(['id'])->on('mp_domains')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropForeign('mp_catalog_items_domain_id_foreign');
        });
    }
};
