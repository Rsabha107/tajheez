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
        Schema::rename('mp_service_option_services', 'mp_service_option_items');

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->after('id');
        });

        DB::table('mp_service_option_items')->select('id')->orderBy('id')->each(function ($row) {
            DB::table('mp_service_option_items')->where('id', $row->id)->update([
                'code' => sprintf('SVC-%05d', $row->id),
            ]);
        });

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->string('code', 40)->nullable(false)->unique()->change();
        });

        Schema::create('mp_bundle_service_options', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bundle_id');
            $table->unsignedBigInteger('service_option_item_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('bundle_id')->references('id')->on('mp_service_options')->onDelete('cascade');
            $table->foreign('service_option_item_id')->references('id')->on('mp_service_option_items')->onDelete('cascade');
            $table->unique(['bundle_id', 'service_option_item_id'], 'bundle_service_options_unique');
        });

        // Every item currently belongs to exactly one bundle (service_option_id) —
        // carry that link forward as its first (only) pivot row before the direct
        // FK is dropped below.
        $now = now();
        DB::table('mp_service_option_items')
            ->select('id', 'service_option_id', 'sort_order')
            ->orderBy('id')
            ->each(function ($row) use ($now) {
                DB::table('mp_bundle_service_options')->insert([
                    'bundle_id' => $row->service_option_id,
                    'service_option_item_id' => $row->id,
                    'sort_order' => $row->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->dropForeign('mp_service_option_services_service_option_id_foreign');
            $table->dropColumn(['service_option_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Best-effort only: an item shared across multiple bundles collapses back
     * to its first (lowest bundle_id) pivot link, since the pre-pivot schema
     * only supported one bundle per item.
     */
    public function down(): void
    {
        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->unsignedBigInteger('service_option_id')->nullable()->after('code');
            $table->unsignedInteger('sort_order')->default(0)->after('service_option_id');
        });

        DB::table('mp_service_option_items')->orderBy('id')->each(function ($item) {
            $first = DB::table('mp_bundle_service_options')
                ->where('service_option_item_id', $item->id)
                ->orderBy('bundle_id')
                ->first();

            if ($first) {
                DB::table('mp_service_option_items')->where('id', $item->id)->update([
                    'service_option_id' => $first->bundle_id,
                    'sort_order' => $first->sort_order,
                ]);
            }
        });

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->unsignedBigInteger('service_option_id')->nullable(false)->change();
            $table->foreign('service_option_id')->references('id')->on('mp_service_options')->onDelete('cascade');
        });

        Schema::dropIfExists('mp_bundle_service_options');

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::rename('mp_service_option_items', 'mp_service_option_services');
    }
};
