<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\MaterialPlanning\RequestLine;
use App\Models\MaterialPlanning\ServiceOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
        $this->assertSameEvent($item->event_id, $materialRequest->event_id, 'sku');

        if (! empty($data['service_option_id'])) {
            $option = ServiceOption::findOrFail($data['service_option_id']);
            $this->assertSameEvent($option->event_id, $materialRequest->event_id, 'service_option_id');
        }

        unset($data['sku']);
        $line = $materialRequest->lines()->create([
            ...$data,
            'catalog_item_id' => $item->id,
            'rate_snapshot' => $item->rate,
            'event_id' => $materialRequest->event_id,
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

        if (! empty($data['service_option_id'])) {
            $option = ServiceOption::findOrFail($data['service_option_id']);
            $this->assertSameEvent($option->event_id, $requestLine->request->event_id, 'service_option_id');
        }

        $requestLine->update($data);

        return response()->json($this->present($requestLine->load(['catalogItem', 'serviceOption'])));
    }

    public function destroy(RequestLine $requestLine)
    {
        Gate::authorize('delete', $requestLine);

        $requestLine->delete();

        return response()->json(['message' => 'Line removed successfully.']);
    }

    /** Assigns one service option bundle to many lines at once, from the Items view's bulk-select. */
    public function bulkAssignServiceOption(Request $request)
    {
        $data = $request->validate([
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer', 'exists:mp_request_lines,id'],
            'service_option_id' => ['required', 'exists:mp_service_options,id'],
        ]);

        $lines = RequestLine::with('request')->whereIn('id', $data['line_ids'])->get();
        $option = ServiceOption::findOrFail($data['service_option_id']);

        foreach ($lines as $line) {
            Gate::authorize('update', $line);
            $this->assertSameEvent($option->event_id, $line->request->event_id, 'service_option_id');
        }

        foreach ($lines as $line) {
            $line->update(['service_option_id' => $data['service_option_id']]);
        }

        $updated = RequestLine::whereIn('id', $data['line_ids'])->with('serviceOption.services.supplier')->get();

        return response()->json([
            'lines' => $updated->map(fn (RequestLine $l) => [
                'id' => $l->id,
                'serviceOptionId' => $l->service_option_id,
                'serviceOptionName' => $l->serviceOption?->name,
                'supplierName' => $l->serviceOption?->services->pluck('supplier.name')->unique()->filter()->implode(', '),
            ])->values()->all(),
        ]);
    }

    /** Catalog items and service options are now single-event; reject cross-event assignment. */
    private function assertSameEvent(int $itemEventId, int $requestEventId, string $field): void
    {
        if ($itemEventId !== $requestEventId) {
            throw ValidationException::withMessages([
                $field => 'This item is not available for the request\'s event.',
            ]);
        }
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
