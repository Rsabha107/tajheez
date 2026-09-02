<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bundle codes (mp_service_options.code) were generated with an
     * "SO-" prefix, the same as the (separate) reusable Service Option
     * Items library — ambiguous given the two are distinct concepts.
     * Bundles now use "BNDL-" instead; existing rows are renamed in place
     * (numeric ids/relations are untouched, only the display code changes).
     */
    public function up(): void
    {
        DB::table('mp_service_options')
            ->where('code', 'like', 'SO-%')
            ->orderBy('id')
            ->select('id', 'code')
            ->each(function ($row) {
                DB::table('mp_service_options')
                    ->where('id', $row->id)
                    ->update(['code' => 'BNDL-' . substr($row->code, 3)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mp_service_options')
            ->where('code', 'like', 'BNDL-%')
            ->orderBy('id')
            ->select('id', 'code')
            ->each(function ($row) {
                DB::table('mp_service_options')
                    ->where('id', $row->id)
                    ->update(['code' => 'SO-' . substr($row->code, 5)]);
            });
    }
};
