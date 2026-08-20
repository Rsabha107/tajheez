<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\MaterialPlanning\RequestLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RequestLineController extends Controller
{
    public function store(Request $request, MaterialRequest $materialRequest)
    {
        $data = $request->validate([
            'sku' => ['required', 'exists:mp_catalog_items,sku'],
            'qty' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:255'],
            'service_option_id' => ['nullable', 'exists:mp_service_options,id'],
        ]);

        $item = CatalogItem::where('sku', $data['sku'])->firstOrFail();
        Gate::authorize('createForRequest', [RequestLine::class, $materialRequest, $item]);

        unset($data['sku']);
        $line = $materialRequest->lines()->create([
            ...$data,
            'catalog_item_id' => $item->id,
            'rate_snapshot' => $item->rate,
        ]);

        return response()->json($this->present($line->load(['catalogItem', 'serviceOption'])), 201);
    }

    public function update(Request $request, RequestLine $requestLine)
    {
        Gate::authorize('update', $requestLine);

        $data = $request->validate([
            'qty' => ['sometimes', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:255'],
            'service_option_id' => ['nullable', 'exists:mp_service_options,id'],
        ]);

        $requestLine->update($data);

        return response()->json($this->present($requestLine->load(['catalogItem', 'serviceOption'])));
    }

    public function destroy(RequestLine $requestLine)
    {
        Gate::authorize('delete', $requestLine);

        $requestLine->delete();

        return response()->json(['message' => 'Line removed successfully.']);
    }

    private function present(RequestLine $line): array
    {
        return [
            'id' => $line->id,
            'sku' => $line->sku,
            'name' => $line->catalogItem?->name,
            'qty' => $line->qty,
            'unit' => $line->unit,
            'rate' => (float) $line->rate_snapshot,
            'comment' => $line->comment,
            'optionId' => $line->serviceOption?->code,
        ];
    }
}
