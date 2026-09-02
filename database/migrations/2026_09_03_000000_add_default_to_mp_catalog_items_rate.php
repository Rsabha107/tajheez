<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rate is no longer required when adding/editing a catalog item (matching
     * stock/baseline, which already default to 0) — give the column the same
     * DB-level default so a value omitted by any client still inserts cleanly.
     */
    public function up(): void
    {
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->decimal('rate', 12, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->decimal('rate', 12, 2)->default(null)->change();
        });
    }
};
