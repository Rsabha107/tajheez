<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\MaterialPlanning\ChangeOrderLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChangeOrderLineController extends Controller
{
    public function store(Request $request, ChangeOrder $changeOrder)
    {
        $data = $request->validate([
            'request_line_id' => ['nullable', 'exists:mp_request_lines,id'],
            'sku' => ['required', 'exists:mp_catalog_items,sku'],
            'qty_before' => ['nullable', 'integer', 'min:0'],
            'qty_after' => ['nullable', 'integer', 'min:0'],
            'rate_before' => ['nullable', 'numeric', 'min:0'],
            'rate_after' => ['nullable', 'numeric', 'min:0'],
            'service_option_before_id' => ['nullable', 'exists:mp_service_options,id'],
            'service_option_after_id' => ['nullable', 'exists:mp_service_options,id'],
            'why' => ['nullable', 'string', 'max:255'],
        ]);

        $item = CatalogItem::findOrFail($data['sku']);
        Gate::authorize('createForChangeOrder', [ChangeOrderLine::class, $changeOrder, $item]);

        $line = $changeOrder->lines()->create($data);

        return response()->json(['id' => $line->id], 201);
    }

    public function update(Request $request, ChangeOrderLine $changeOrderLine)
    {
        Gate::authorize('update', $changeOrderLine);

        $data = $request->validate([
            'qty_after' => ['nullable', 'integer', 'min:0'],
            'rate_after' => ['nullable', 'numeric', 'min:0'],
            'service_option_after_id' => ['nullable', 'exists:mp_service_options,id'],
            'why' => ['nullable', 'string', 'max:255'],
        ]);

        $changeOrderLine->update($data);

        return response()->json(['id' => $changeOrderLine->id]);
    }

    public function destroy(ChangeOrderLine $changeOrderLine)
    {
        Gate::authorize('delete', $changeOrderLine);

        $changeOrderLine->delete();

        return response()->json(['message' => 'Change order line removed successfully.']);
    }
}
