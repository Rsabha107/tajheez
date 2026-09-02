<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * item_group_id/item_subgroup_id were nullable on both
     * mp_service_option_items and mp_service_options, and the vast majority
     * of existing rows (57/61 items, 40/44 bundles) had neither set. There is
     * no reliable signal to infer their real classification from, so a
     * single catch-all "Uncategorized" group/subgroup is created and every
     * null row is backfilled to it before the columns are tightened to
     * NOT NULL — existing data stays valid, and admins can reclassify
     * individual rows later via the (now-required) edit forms. The
     * catch-all's domain_id is arbitrary (group selection isn't
     * domain-filtered in the UI) — picked as the lowest domain id.
     */
    public function up(): void
    {
        $now = now();
        $domainId = DB::table('mp_domains')->orderBy('id')->value('id');

        $groupId = DB::table('mp_item_groups')->insertGetId([
            'code' => 'UNCATEGORIZED',
            'status_id' => 1,
            'domain_id' => $domainId,
            'label' => 'Uncategorized',
            'description' => 'Catch-all for service options/bundles with no real classification yet.',
            'sort_order' => 999,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subgroupId = DB::table('mp_item_subgroups')->insertGetId([
            'code' => 'UNCATEGORIZED',
            'status_id' => 1,
            'group_id' => $groupId,
            'name' => 'Uncategorized',
            'description' => 'Catch-all for service options/bundles with no real classification yet.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('mp_service_option_items')->whereNull('item_group_id')->update(['item_group_id' => $groupId]);
        DB::table('mp_service_option_items')->whereNull('item_subgroup_id')->update(['item_subgroup_id' => $subgroupId]);
        DB::table('mp_service_options')->whereNull('item_group_id')->update(['item_group_id' => $groupId]);
        DB::table('mp_service_options')->whereNull('item_subgroup_id')->update(['item_subgroup_id' => $subgroupId]);

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->dropForeign(['item_group_id']);
            $table->dropForeign(['item_subgroup_id']);
        });
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['item_group_id']);
            $table->dropForeign(['item_subgroup_id']);
        });

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_group_id')->nullable(false)->change();
            $table->unsignedBigInteger('item_subgroup_id')->nullable(false)->change();
            $table->foreign('item_group_id')->references('id')->on('mp_item_groups')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('item_subgroup_id')->references('id')->on('mp_item_subgroups')->onUpdate('no action')->onDelete('restrict');
        });
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->unsignedBigInteger('item_group_id')->nullable(false)->change();
            $table->unsignedBigInteger('item_subgroup_id')->nullable(false)->change();
            $table->foreign('item_group_id')->references('id')->on('mp_item_groups')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('item_subgroup_id')->references('id')->on('mp_item_subgroups')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Best-effort: columns go back to nullable with the original
     * nullOnDelete FK behavior. Rows backfilled to the "Uncategorized"
     * catch-all are left pointing at it rather than reverted to null — the
     * catch-all row itself is intentionally left in place either way.
     */
    public function down(): void
    {
        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->dropForeign(['item_group_id']);
            $table->dropForeign(['item_subgroup_id']);
            $table->unsignedBigInteger('item_group_id')->nullable()->change();
            $table->unsignedBigInteger('item_subgroup_id')->nullable()->change();
            $table->foreign('item_group_id')->references('id')->on('mp_item_groups')->nullOnDelete();
            $table->foreign('item_subgroup_id')->references('id')->on('mp_item_subgroups')->nullOnDelete();
        });
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['item_group_id']);
            $table->dropForeign(['item_subgroup_id']);
            $table->unsignedBigInteger('item_group_id')->nullable()->change();
            $table->unsignedBigInteger('item_subgroup_id')->nullable()->change();
            $table->foreign('item_group_id')->references('id')->on('mp_item_groups')->nullOnDelete();
            $table->foreign('item_subgroup_id')->references('id')->on('mp_item_subgroups')->nullOnDelete();
        });
    }
};
