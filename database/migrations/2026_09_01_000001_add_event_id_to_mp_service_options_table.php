<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any bundle with no request/change-order-line history
     * to infer an event from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * Service option bundles were global until now. To make them strictly
     * one-event-each (matching mp_requests.event_id), each bundle is
     * backfilled from the events of the request/change-order lines that
     * already reference it:
     *   - referenced by exactly one event -> stamped with that event.
     *   - referenced by 2+ events -> the original row keeps the lowest-id
     *     event; a duplicate row (new code, cloned mp_bundle_service_options
     *     pivot rows) is created per additional event, and that event's
     *     lines are repointed to the duplicate.
     *   - referenced by no events -> falls back to DEFAULT_EVENT_ID.
     */
    public function up(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('id');
        });

        $eventsByOption = collect();

        DB::table('mp_request_lines')
            ->join('mp_requests', 'mp_requests.id', '=', 'mp_request_lines.request_id')
            ->whereNotNull('mp_request_lines.service_option_id')
            ->select('mp_request_lines.service_option_id as option_id', 'mp_requests.event_id')
            ->distinct()
            ->get()
            ->each(fn ($row) => $eventsByOption->push($row));

        DB::table('mp_change_order_lines')
            ->join('mp_change_orders', 'mp_change_orders.id', '=', 'mp_change_order_lines.change_order_id')
            ->join('mp_requests', 'mp_requests.id', '=', 'mp_change_orders.request_id')
            ->whereNotNull('mp_change_order_lines.service_option_before_id')
            ->select('mp_change_order_lines.service_option_before_id as option_id', 'mp_requests.event_id')
            ->distinct()
            ->get()
            ->each(fn ($row) => $eventsByOption->push($row));

        DB::table('mp_change_order_lines')
            ->join('mp_change_orders', 'mp_change_orders.id', '=', 'mp_change_order_lines.change_order_id')
            ->join('mp_requests', 'mp_requests.id', '=', 'mp_change_orders.request_id')
            ->whereNotNull('mp_change_order_lines.service_option_after_id')
            ->select('mp_change_order_lines.service_option_after_id as option_id', 'mp_requests.event_id')
            ->distinct()
            ->get()
            ->each(fn ($row) => $eventsByOption->push($row));

        $eventsByOption = $eventsByOption->groupBy('option_id');

        $options = DB::table('mp_service_options')->orderBy('id')->get();

        foreach ($options as $option) {
            $eventIds = ($eventsByOption[$option->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                DB::table('mp_service_options')->where('id', $option->id)->update([
                    'event_id' => self::DEFAULT_EVENT_ID,
                ]);
                continue;
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_service_options')->where('id', $option->id)->update([
                'event_id' => $primaryEventId,
            ]);

            $pivots = DB::table('mp_bundle_service_options')->where('bundle_id', $option->id)->get();

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_service_options')->insertGetId([
                    'code' => substr($option->code, 0, 40 - strlen($suffix)) . $suffix,
                    'event_id' => $extraEventId,
                    'name' => $option->name,
                    'classification_id' => $option->classification_id,
                    'status_id' => $option->status_id,
                    'is_default' => $option->is_default,
                    'item_group_id' => $option->item_group_id,
                    'item_subgroup_id' => $option->item_subgroup_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($pivots as $pivot) {
                    DB::table('mp_bundle_service_options')->insert([
                        'bundle_id' => $newId,
                        'service_option_item_id' => $pivot->service_option_item_id,
                        'sort_order' => $pivot->sort_order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('mp_request_lines')
                    ->join('mp_requests', 'mp_requests.id', '=', 'mp_request_lines.request_id')
                    ->where('mp_request_lines.service_option_id', $option->id)
                    ->where('mp_requests.event_id', $extraEventId)
                    ->update(['mp_request_lines.service_option_id' => $newId]);

                DB::table('mp_change_order_lines')
                    ->join('mp_change_orders', 'mp_change_orders.id', '=', 'mp_change_order_lines.change_order_id')
                    ->join('mp_requests', 'mp_requests.id', '=', 'mp_change_orders.request_id')
                    ->where('mp_change_order_lines.service_option_before_id', $option->id)
                    ->where('mp_requests.event_id', $extraEventId)
                    ->update(['mp_change_order_lines.service_option_before_id' => $newId]);

                DB::table('mp_change_order_lines')
                    ->join('mp_change_orders', 'mp_change_orders.id', '=', 'mp_change_order_lines.change_order_id')
                    ->join('mp_requests', 'mp_requests.id', '=', 'mp_change_orders.request_id')
                    ->where('mp_change_order_lines.service_option_after_id', $option->id)
                    ->where('mp_requests.event_id', $extraEventId)
                    ->update(['mp_change_order_lines.service_option_after_id' => $newId]);
            }
        }

        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_service_options_event_id_index');
            $table->foreign('event_id')->references('id')->on('events')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Best-effort only: duplicate rows created for shared bundles (and their
     * cloned pivot rows) are left in place (dropping them could orphan the
     * request/change-order lines that were repointed to them during up()).
     */
    public function down(): void
    {
        Schema::table('mp_service_options', function (Blueprint $table) {
            $table->dropForeign('mp_service_options_event_id_foreign');
            $table->dropIndex('mp_service_options_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
