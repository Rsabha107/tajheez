<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fallback event for any domain with no catalog-item history to infer an
     * event from (matches MaterialPlanningSeeder::EVENT_ID).
     */
    private const DEFAULT_EVENT_ID = 10;

    /**
     * Run the migrations.
     *
     * Domains were global until now. To make them strictly one-event-each
     * (matching mp_catalog_items.event_id), each domain is backfilled from
     * the events of the catalog items that already reference it:
     *   - referenced by exactly one event -> stamped with that event.
     *   - referenced by 2+ events -> the original row keeps the lowest-id
     *     event; a duplicate row (new code) is created per additional event,
     *     and that event's catalog items are repointed to the duplicate.
     *   - referenced by no events -> falls back to DEFAULT_EVENT_ID.
     *
     * mp_item_groups.domain_id is NOT repointed here — item groups aren't
     * event-scoped yet at this point in the migration sequence; that FK is
     * repaired by the mp_item_groups event_id migration instead.
     */
    public function up(): void
    {
        Schema::table('mp_domains', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('code');
        });

        $eventsByDomain = DB::table('mp_catalog_items')
            ->select('domain_id', 'event_id')
            ->distinct()
            ->get()
            ->groupBy('domain_id');

        $domains = DB::table('mp_domains')->orderBy('id')->get();

        foreach ($domains as $domain) {
            $eventIds = ($eventsByDomain[$domain->id] ?? collect())
                ->pluck('event_id')
                ->unique()
                ->sort()
                ->values();

            if ($eventIds->isEmpty()) {
                DB::table('mp_domains')->where('id', $domain->id)->update([
                    'event_id' => self::DEFAULT_EVENT_ID,
                ]);
                continue;
            }

            $primaryEventId = $eventIds->first();
            DB::table('mp_domains')->where('id', $domain->id)->update([
                'event_id' => $primaryEventId,
            ]);

            foreach ($eventIds->slice(1) as $extraEventId) {
                $suffix = '-E' . $extraEventId;
                $newId = DB::table('mp_domains')->insertGetId([
                    'code' => substr($domain->code, 0, 8 - strlen($suffix)) . $suffix,
                    'event_id' => $extraEventId,
                    'status_id' => $domain->status_id,
                    'label' => $domain->label,
                    'color' => $domain->color,
                    'chip' => $domain->chip,
                    'description' => $domain->description,
                    'sort_order' => $domain->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('mp_catalog_items')
                    ->where('domain_id', $domain->id)
                    ->where('event_id', $extraEventId)
                    ->update(['domain_id' => $newId]);
            }
        }

        Schema::table('mp_domains', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->index('event_id', 'mp_domains_event_id_index');
            $table->foreign('event_id')->references('id')->on('events')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Best-effort only: duplicate rows created for shared domains are left in
     * place (dropping them could orphan the catalog items that were
     * repointed to them during up()).
     */
    public function down(): void
    {
        Schema::table('mp_domains', function (Blueprint $table) {
            $table->dropForeign('mp_domains_event_id_foreign');
            $table->dropIndex('mp_domains_event_id_index');
            $table->dropColumn('event_id');
        });
    }
};
