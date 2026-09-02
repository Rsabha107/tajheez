<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any catalog item with no request-line history to infer
     * an event from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * Catalog items were global until now. To make them strictly one-event-each
     * (matching mp_requests.event_id), each item is backfilled from the events
     * of the request lines that already reference it:
     *   - referenced by exactly one event -> stamped with that event.
     *   - referenced by 2+ events -> the original row keeps the lowest-id event;
     *     a duplicate row (new sku) is created per additional event, and that
     *     event's request/change-order lines are repointed to the duplicate.
     *   - referenced by no events -> falls back to DEFAULT_EVENT_ID.
     */
    public function up(): void
    {
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('domain_id');
        });

        $eventsByItem = DB::table('mp_request_lines')
            ->join('mp_requests', 'mp_requests.id', '=', 'mp_request_lines.request_id')
            ->select('mp_request_lines.catalog_item_id', 'mp_requests.event_id')
            ->distinct()
            ->get()
            ->groupBy('catalog_item_id');

        $items = DB::table('mp_catalog_items')->orderBy('id')->get();

        foreach ($items as $item) {
            $eventIds = ($eventsByItem[$item->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                DB::table('mp_catalog_items')->where('id', $item->id)->update([
                    'event_id' => self::DEFAULT_EVENT_ID,
                ]);
                continue;
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_catalog_items')->where('id', $item->id)->update([
                'event_id' => $primaryEventId,
            ]);

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_catalog_items')->insertGetId([
                    'sku' => substr($item->sku, 0, 20 - strlen($suffix)) . $suffix,
                    'domain_id' => $item->domain_id,
                    'event_id' => $extraEventId,
                    'group' => $item->group,
                    'sub' => $item->sub,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'rate' => $item->rate,
                    'stock' => $item->stock,
                    'baseline' => $item->baseline,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('mp_request_lines')
                    ->join('mp_requests', 'mp_requests.id', '=', 'mp_request_lines.request_id')
                    ->where('mp_request_lines.catalog_item_id', $item->id)
                    ->where('mp_requests.event_id', $extraEventId)
                    ->update(['mp_request_lines.catalog_item_id' => $newId]);

                DB::table('mp_change_order_lines')
                    ->join('mp_change_orders', 'mp_change_orders.id', '=', 'mp_change_order_lines.change_order_id')
                    ->join('mp_requests', 'mp_requests.id', '=', 'mp_change_orders.request_id')
                    ->where('mp_change_order_lines.catalog_item_id', $item->id)
                    ->where('mp_requests.event_id', $extraEventId)
                    ->update(['mp_change_order_lines.catalog_item_id' => $newId]);
            }
        }

        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_catalog_items_event_id_index');
            $table->foreign('event_id')->references('id')->on('events')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Best-effort only: duplicate rows created for shared items are left in
     * place (dropping them could orphan the request/change-order lines that
     * were repointed to them during up()).
     */
    public function down(): void
    {
        Schema::table('mp_catalog_items', function (Blueprint $table) {
            $table->dropForeign('mp_catalog_items_event_id_foreign');
            $table->dropIndex('mp_catalog_items_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
