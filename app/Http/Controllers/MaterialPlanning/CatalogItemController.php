<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ChangeOrderLine;
use App\Models\MaterialPlanning\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CatalogItemController extends Controller
{
    public function data()
    {
        $items = CatalogItem::orderBy('sku')->get();

        return response()->json($items->map(fn (CatalogItem $c) => $this->present($c))->values()->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:20', 'unique:mp_catalog_items,sku'],
            'event_id' => ['required', 'exists:events,id'],
            'domain_code' => ['required', Rule::exists('mp_domains', 'code')->where('event_id', $request->input('event_id'))],
            'group' => ['required', 'string', 'max:60'],
            'sub' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:180'],
            'unit' => ['required', 'string', 'max:20'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'baseline' => ['nullable', 'integer', 'min:0'],
        ]);

        Gate::authorize('create', [CatalogItem::class, $data['domain_code']]);

        $data['domain_id'] = Domain::where('code', $data['domain_code'])->where('event_id', $data['event_id'])->value('id');
        unset($data['domain_code']);
        $data['rate'] = $data['rate'] ?? 0;
        $data['stock'] = $data['stock'] ?? 0;
        $data['baseline'] = $data['baseline'] ?? 0;
        $item = CatalogItem::create($data);

        return response()->json($this->present($item), 201);
    }

    public function update(Request $request, CatalogItem $catalogItem)
    {
        Gate::authorize('update', $catalogItem);

        $data = $request->validate([
            'event_id' => ['sometimes', 'exists:events,id'],
            'group' => ['sometimes', 'string', 'max:60'],
            'sub' => ['sometimes', 'string', 'max:60'],
            'name' => ['sometimes', 'string', 'max:180'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'baseline' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('rate', $data) && $data['rate'] === null) $data['rate'] = 0;
        if (array_key_exists('baseline', $data) && $data['baseline'] === null) $data['baseline'] = 0;

        $catalogItem->update($data);

        return response()->json($this->present($catalogItem));
    }

    public function destroy(CatalogItem $catalogItem)
    {
        Gate::authorize('delete', $catalogItem);

        // mp_request_lines/mp_change_order_lines both FK-restrict on catalog_item_id,
        // so check usage up front rather than letting the delete fail with a bare 500.
        $requestCodes = $catalogItem->requestLines()
            ->with('request:id,code')
            ->get()
            ->pluck('request.code')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $changeOrderCodes = ChangeOrderLine::where('catalog_item_id', $catalogItem->id)
            ->with('changeOrder:id,code')
            ->get()
            ->pluck('changeOrder.code')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($requestCodes->isNotEmpty() || $changeOrderCodes->isNotEmpty()) {
            return response()->json([
                'message' => "This SKU is still in use and can't be removed.",
                'usage' => [
                    'requests' => $requestCodes->values()->all(),
                    'changeOrders' => $changeOrderCodes->values()->all(),
                ],
            ], 422);
        }

        $catalogItem->delete();

        return response()->json(['message' => 'Catalog item deleted successfully.']);
    }

    /** Shape matches the mock catalog row exactly, for drop-in frontend compatibility. */
    private function present(CatalogItem $item): array
    {
        return [
            'sku' => $item->sku,
            // Real numeric PK — `sku` is the human-readable identifier used
            // everywhere else in the UI, but PUT/DELETE against a specific
            // item needs the actual FK value (mp_catalog_items.id).
            'dbId' => $item->id,
            'domain' => $item->domain_code,
            'eventId' => $item->event_id,
            'group' => $item->group,
            'sub' => $item->sub,
            'name' => $item->name,
            'unit' => $item->unit,
            'rate' => (float) $item->rate,
            'stock' => $item->stock ?? 0,
            'baseline' => $item->baseline,
        ];
    }
}
