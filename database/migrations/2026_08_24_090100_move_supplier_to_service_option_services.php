<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mp_service_option_services', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('service_option_id');
        });

        // Different services within a bundle can come from different suppliers
        // (e.g. TV from one supplier, STP from another) — backfill each service
        // line with the bundle's current supplier before it stops being a
        // bundle-level field below.
        DB::table('mp_service_options')
            ->select('id', 'supplier_id')
            ->orderBy('id')
            ->each(function ($option) {
                DB::table('mp_service_option_services')
                    ->where('service_option_id', $option->id)
                    ->update(['supplier_id' => $option->supplier_id]);
            });

        DB::statement('ALTER TABLE mp_service_option_services MODIFY supplier_id BIGINT UNSIGNED NOT NULL');

        Schema::table('mp_service_option_services', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('mp_suppliers')->onDelete('restrict');
        });

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign('mp_service_options_supplier_id_foreign');
            $table->dropColumn('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Best-effort: a bundle whose services span multiple suppliers collapses
     * back to its first service's supplier.
     */
    public function down(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('code');
        });

        DB::table('mp_service_options')->orderBy('id')->each(function ($option) {
            $first = DB::table('mp_service_option_services')
                ->where('service_option_id', $option->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($first) {
                DB::table('mp_service_options')->where('id', $option->id)->update([
                    'supplier_id' => $first->supplier_id,
                ]);
            }
        });

        Schema::table('mp_service_option_services', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
