<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\CatalogItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CatalogItemController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:20', 'unique:mp_catalog_items,sku'],
            'domain_code' => ['required', 'exists:mp_domains,code'],
            'group' => ['required', 'string', 'max:60'],
            'sub' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:180'],
            'unit' => ['required', 'string', 'max:20'],
            'rate' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'baseline' => ['required', 'integer', 'min:0'],
        ]);

        Gate::authorize('create', [CatalogItem::class, $data['domain_code']]);

        $data['stock'] = $data['stock'] ?? 0;
        $item = CatalogItem::create($data);
        $item->ensureOwnPoolOption();

        return response()->json($this->present($item), 201);
    }

    public function update(Request $request, CatalogItem $catalogItem)
    {
        Gate::authorize('update', $catalogItem);

        $data = $request->validate([
            'group' => ['sometimes', 'string', 'max:60'],
            'sub' => ['sometimes', 'string', 'max:60'],
            'name' => ['sometimes', 'string', 'max:180'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'rate' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'baseline' => ['sometimes', 'integer', 'min:0'],
        ]);

        $catalogItem->update($data);

        return response()->json($this->present($catalogItem));
    }

    public function destroy(CatalogItem $catalogItem)
    {
        Gate::authorize('delete', $catalogItem);

        $catalogItem->delete();

        return response()->json(['message' => 'Catalog item deleted successfully.']);
    }

    /** Shape matches the mock catalog row exactly, for drop-in frontend compatibility. */
    private function present(CatalogItem $item): array
    {
        return [
            'sku' => $item->sku,
            'domain' => $item->domain_code,
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
