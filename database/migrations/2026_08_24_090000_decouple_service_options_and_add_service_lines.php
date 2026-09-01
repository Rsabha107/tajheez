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
        Schema::create('mp_service_option_services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('service_option_id')->index();
            $table->string('name', 180);
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedInteger('lead_days')->default(0);
            $table->string('sla', 120)->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->string('contract_reference', 60)->nullable();
            $table->text('spec')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('service_option_id')->references('id')->on('mp_service_options')->onDelete('cascade');
        });

        // Service options were previously one-item-per-row (cost/lead/sla/capacity/
        // contract/spec lived on the option itself). Carry each existing option's
        // values forward as its first service line before those columns are dropped
        // below, so the 2026-08-19 seed data isn't lost by the decoupling.
        $now = now();
        DB::table('mp_service_options')
            ->select('id', 'name', 'cost', 'lead_days', 'sla', 'capacity', 'contract_reference', 'spec')
            ->orderBy('id')
            ->each(function ($row) use ($now) {
                DB::table('mp_service_option_services')->insert([
                    'service_option_id' => $row->id,
                    'name' => $row->name,
                    'cost' => $row->cost,
                    'lead_days' => $row->lead_days,
                    'sla' => $row->sla,
                    'capacity' => $row->capacity,
                    'contract_reference' => $row->contract_reference,
                    'spec' => $row->spec,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign('mp_service_options_catalog_item_id_foreign');
            $table->dropColumn(['catalog_item_id', 'cost', 'lead_days', 'sla', 'capacity', 'contract_reference', 'spec']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Best-effort only: a bundle that ended up with multiple service lines
     * collapses back to its first line, and catalog_item_id cannot be restored
     * (the link is intentionally removed), so it comes back nullable.
     */
    public function down(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_item_id')->nullable()->after('code');
            $table->decimal('cost', 12)->default(0)->after('name');
            $table->unsignedInteger('lead_days')->default(0)->after('cost');
            $table->string('sla', 120)->nullable()->after('lead_days');
            $table->unsignedInteger('capacity')->default(0)->after('sla');
            $table->string('contract_reference', 60)->nullable()->after('capacity');
            $table->text('spec')->nullable()->after('contract_reference');
        });

        DB::table('mp_service_options')->orderBy('id')->each(function ($option) {
            $first = DB::table('mp_service_option_services')
                ->where('service_option_id', $option->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($first) {
                DB::table('mp_service_options')->where('id', $option->id)->update([
                    'cost' => $first->cost,
                    'lead_days' => $first->lead_days,
                    'sla' => $first->sla,
                    'capacity' => $first->capacity,
                    'contract_reference' => $first->contract_reference,
                    'spec' => $first->spec,
                ]);
            }
        });

        Schema::dropIfExists('mp_service_option_services');
    }
};
