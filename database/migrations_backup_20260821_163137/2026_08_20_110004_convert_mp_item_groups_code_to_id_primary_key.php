<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mp_item_subgroups.group_code still FKs to mp_item_groups.code — drop
     * it first (MySQL refuses to drop a PRIMARY KEY a foreign key still
     * depends on), it's re-established against group_id in the
     * mp_item_subgroups conversion migration. mp_item_groups.domain_code's
     * own FK to mp_domains was already dropped by the mp_domains
     * conversion migration, so it isn't dropped again here.
     */
    public function up(): void
    {
        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->dropForeign(['group_code']);
        });

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropPrimary(['code']);
            $table->id()->first();
            $table->unique('code');
        });

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('domain_id')->nullable()->after('domain_code');
        });

        DB::statement('UPDATE mp_item_groups g JOIN mp_domains d ON g.domain_code = d.code SET g.domain_id = d.id');

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropIndex(['domain_code']);
            $table->dropColumn('domain_code');
        });

        DB::statement('ALTER TABLE mp_item_groups MODIFY domain_id BIGINT UNSIGNED NOT NULL');

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->foreign('domain_id')->references('id')->on('mp_domains')->restrictOnDelete();
            $table->index('domain_id');
        });
    }

    /**
     * Runs after the mp_item_subgroups conversion migration has already
     * been rolled back, so mp_item_subgroups.group_code (FK ->
     * mp_item_groups.code) already exists again at this point.
     */
    public function down(): void
    {
        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->dropForeign(['group_code']);
        });

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
            $table->dropIndex(['domain_id']);
            $table->string('domain_code', 8)->nullable()->after('code');
        });

        DB::statement('UPDATE mp_item_groups g JOIN mp_domains d ON g.domain_id = d.id SET g.domain_code = d.code');

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->dropColumn('domain_id');
        });

        DB::statement('ALTER TABLE mp_item_groups MODIFY domain_code VARCHAR(8) NOT NULL');

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->index('domain_code');
            $table->dropUnique(['code']);
            $table->dropColumn('id');
            $table->primary('code');
        });

        Schema::table('mp_item_groups', function (Blueprint $table) {
            $table->foreign('domain_code')->references('code')->on('mp_domains')->restrictOnDelete();
        });
        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->foreign('group_code')->references('code')->on('mp_item_groups')->restrictOnDelete();
        });
    }
};
