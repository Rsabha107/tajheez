<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * group_code's FK to mp_item_groups was already dropped by the
     * mp_item_groups conversion migration — only the index remains here.
     */
    public function up(): void
    {
        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('group_code');
        });

        DB::statement('UPDATE mp_item_subgroups s JOIN mp_item_groups g ON s.group_code = g.code SET s.group_id = g.id');

        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->dropIndex(['group_code']);
            $table->dropColumn('group_code');
        });

        DB::statement('ALTER TABLE mp_item_subgroups MODIFY group_id BIGINT UNSIGNED NOT NULL');

        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->foreign('group_id')->references('id')->on('mp_item_groups')->restrictOnDelete();
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id']);
            $table->string('group_code', 20)->nullable()->after('code');
        });

        DB::statement('UPDATE mp_item_subgroups s JOIN mp_item_groups g ON s.group_id = g.id SET s.group_code = g.code');

        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });

        DB::statement('ALTER TABLE mp_item_subgroups MODIFY group_code VARCHAR(20) NOT NULL');

        Schema::table('mp_item_subgroups', function (Blueprint $table) {
            $table->foreign('group_code')->references('code')->on('mp_item_groups')->restrictOnDelete();
            $table->index('group_code');
        });
    }
};
