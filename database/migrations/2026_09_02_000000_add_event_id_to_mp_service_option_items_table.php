<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any library item with no bundle usage to infer an
     * event from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * The reusable Service Option Items library was global until now (bundles
     * pick from it via mp_bundle_service_options). To make it strictly
     * one-event-each like mp_catalog_items/mp_service_options, each item is
     * backfilled from the events of the bundles that already pick it:
     *   - picked by bundles in exactly one event -> stamped with that event.
     *   - picked by bundles in 2+ events -> the original row keeps the
     *     lowest-id event; a duplicate row (new code) is created per
     *     additional event, and that event's bundles' pivot rows are
     *     repointed to the duplicate.
     *   - picked by no bundles -> falls back to DEFAULT_EVENT_ID.
     */
    public function up(): void
    {
        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('code');
        });

        $eventsByItem = DB::table('mp_bundle_service_options')
            ->join('mp_service_options', 'mp_service_options.id', '=', 'mp_bundle_service_options.bundle_id')
            ->select('mp_bundle_service_options.service_option_item_id', 'mp_service_options.event_id')
            ->distinct()
            ->get()
            ->groupBy('service_option_item_id');

        $items = DB::table('mp_service_option_items')->orderBy('id')->get();

        foreach ($items as $item) {
            $eventIds = ($eventsByItem[$item->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                DB::table('mp_service_option_items')->where('id', $item->id)->update([
                    'event_id' => self::DEFAULT_EVENT_ID,
                ]);
                continue;
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_service_option_items')->where('id', $item->id)->update([
                'event_id' => $primaryEventId,
            ]);

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_service_option_items')->insertGetId([
                    'code' => substr($item->code, 0, 40 - strlen($suffix)) . $suffix,
                    'event_id' => $extraEventId,
                    'name' => $item->name,
                    'supplier_id' => $item->supplier_id,
                    'cost' => $item->cost,
                    'lead_days' => $item->lead_days,
                    'sla' => $item->sla,
                    'capacity' => $item->capacity,
                    'contract_reference' => $item->contract_reference,
                    'spec' => $item->spec,
                    'item_group_id' => $item->item_group_id,
                    'item_subgroup_id' => $item->item_subgroup_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Repoint just this event's bundles' pivot rows to the duplicate item.
                $pivotIds = DB::table('mp_bundle_service_options')
                    ->join('mp_service_options', 'mp_service_options.id', '=', 'mp_bundle_service_options.bundle_id')
                    ->where('mp_bundle_service_options.service_option_item_id', $item->id)
                    ->where('mp_service_options.event_id', $extraEventId)
                    ->pluck('mp_bundle_service_options.id');

                DB::table('mp_bundle_service_options')
                    ->whereIn('id', $pivotIds)
                    ->update(['service_option_item_id' => $newId]);
            }
        }

        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_service_option_items_event_id_index');
            $table->foreign('event_id')->references('id')->on('events')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Best-effort only: duplicate rows created for shared items are left in
     * place (dropping them could orphan the mp_bundle_service_options pivot
     * rows that were repointed to them during up()).
     */
    public function down(): void
    {
        Schema::table('mp_service_option_items', function (Blueprint $table) {
            $table->dropForeign('mp_service_option_items_event_id_foreign');
            $table->dropIndex('mp_service_option_items_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
