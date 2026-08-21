<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The old free-text workflow tier (preferred|active|suspended) becomes a
     * proper FK to classifications (Preferred|Normal|Suspended — "active"
     * maps to "Normal"). A separate, unrelated status_id (active/inactive
     * record state, FK -> global_statuses) is added alongside it.
     */
    public function up(): void
    {
        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->unsignedInteger('classification_id')->nullable()->after('status');
        });

        DB::statement("UPDATE mp_suppliers SET classification_id = CASE status WHEN 'preferred' THEN 1 WHEN 'suspended' THEN 3 ELSE 2 END");

        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        DB::statement('ALTER TABLE mp_suppliers MODIFY classification_id INT UNSIGNED NOT NULL');

        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->foreign('classification_id')->references('id')->on('classifications')->restrictOnDelete();
            $table->index('classification_id');

            $table->integer('status_id')->default(1)->after('classification_id');
            $table->foreign('status_id')->references('id')->on('global_statuses')->restrictOnDelete();
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropIndex(['status_id']);
            $table->dropColumn('status_id');

            $table->dropForeign(['classification_id']);
            $table->dropIndex(['classification_id']);
            $table->string('status', 20)->nullable()->after('kind');
        });

        DB::statement("UPDATE mp_suppliers SET status = CASE classification_id WHEN 1 THEN 'preferred' WHEN 3 THEN 'suspended' ELSE 'active' END");

        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropColumn('classification_id');
        });

        DB::statement("ALTER TABLE mp_suppliers MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active'");
    }
};
