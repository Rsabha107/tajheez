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
        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_group_id')->nullable()->after('supplier_id');
            $table->unsignedBigInteger('item_subgroup_id')->nullable()->after('item_group_id');
            $table->foreign('item_group_id')->references('id')->on('mp_item_groups')->nullOnDelete();
            $table->foreign('item_subgroup_id')->references('id')->on('mp_item_subgroups')->nullOnDelete();
        });

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->unsignedBigInteger('item_group_id')->nullable()->after('status_id');
            $table->unsignedBigInteger('item_subgroup_id')->nullable()->after('item_group_id');
            $table->foreign('item_group_id')->references('id')->on('mp_item_groups')->nullOnDelete();
            $table->foreign('item_subgroup_id')->references('id')->on('mp_item_subgroups')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->dropForeign(['item_group_id']);
            $table->dropForeign(['item_subgroup_id']);
            $table->dropColumn(['item_group_id', 'item_subgroup_id']);
        });

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign(['item_group_id']);
            $table->dropForeign(['item_subgroup_id']);
            $table->dropColumn(['item_group_id', 'item_subgroup_id']);
        });
    }
};
