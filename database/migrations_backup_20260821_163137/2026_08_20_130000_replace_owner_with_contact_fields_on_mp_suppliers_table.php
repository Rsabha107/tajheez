<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * owner_user_id pointed at an internal Tajheez user managing the
     * relationship — replaced with plain contact_name/contact_phone for the
     * supplier's own point of contact, which isn't a system user.
     */
    public function up(): void
    {
        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn('owner_user_id');

            $table->string('contact_name', 150)->nullable()->after('msa_reference');
            $table->string('contact_phone', 30)->nullable()->after('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('mp_suppliers', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone']);

            $table->foreignId('owner_user_id')->nullable()->after('msa_reference')->constrained('users')->nullOnDelete();
        });
    }
};
