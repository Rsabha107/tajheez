<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\MaterialPlanning\ChangeOrderLine;
use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\MaterialPlanning\RequestActivity;
use App\Models\MaterialPlanning\RequestApprovalStep;
use App\Models\MaterialPlanning\RequestLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MaterialRequestController extends Controller
{
    /**
     * Full detail for one request — lines/approval steps/activity/change
     * orders are never included in the index() listing payload, so the
     * frontend fetches this fresh every time a request is opened rather
     * than relying on the (summary-only, page-load-time) `requests` prop.
     */
    public function show(string $code)
    {
        $materialRequest = MaterialRequest::with([
            'venue', 'owner', 'functionalArea',
            'lines.catalogItem', 'lines.serviceOption.supplier',
            'approvalSteps.approver',
            'activity.actor',
            'changeOrders.raisedBy', 'changeOrders.lines.catalogItem',
        ])->where('code', $code)->firstOrFail();

        Gate::authorize('view', $materialRequest);

        return response()->json($this->presentDetail($materialRequest));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', MaterialRequest::class);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'event_id' => ['required', 'exists:events,id'],
            'venue_id' => ['required', 'exists:venues,id'],
            'functional_area_id' => ['nullable', 'exists:functional_areas,id'],
            'site_type' => ['nullable', 'string', 'max:80'],
            'site_code' => ['nullable', 'string', 'max:40'],
            'site_name' => ['nullable', 'string', 'max:120'],
            'ls_category' => ['nullable', 'string', 'max:40'],
            'ls_sub' => ['nullable', 'string', 'max:40'],
            'ls_name' => ['nullable', 'string', 'max:80'],
            'ls_code' => ['nullable', 'string', 'max:20'],
            'base_room' => ['nullable', 'in:No,Yes — shared,Yes — dedicated'],
            'move_in' => ['nullable', 'date'],
            'move_out' => ['nullable', 'date'],
            'priority' => ['nullable', 'in:Low,Medium,High,Critical'],
            'approver_routing' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'layout_file' => ['nullable', 'file', 'max:20480'],
        ]);

        $data['owner_user_id'] = $request->user()->id;
        $data['status'] = 'draft';

        if ($request->hasFile('layout_file')) {
            $file = $request->file('layout_file');
            $data['layout_file_path'] = $file->store('mp-request-layouts', 'public');
            $data['layout_file_name'] = $file->getClientOriginalName();
        }
        unset($data['layout_file']);

        $materialRequest = MaterialRequest::create($data);

        return response()->json($this->present($materialRequest), 201);
    }

    public function update(Request $request, MaterialRequest $materialRequest)
    {
        Gate::authorize('update', $materialRequest);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'event_id' => ['sometimes', 'exists:events,id'],
            'venue_id' => ['sometimes', 'exists:venues,id'],
            'functional_area_id' => ['nullable', 'exists:functional_areas,id'],
            'site_type' => ['nullable', 'string', 'max:80'],
            'site_code' => ['nullable', 'string', 'max:40'],
            'site_name' => ['sometimes', 'string', 'max:120'],
            'ls_category' => ['nullable', 'string', 'max:40'],
            'ls_sub' => ['nullable', 'string', 'max:40'],
            'ls_name' => ['nullable', 'string', 'max:80'],
            'ls_code' => ['nullable', 'string', 'max:20'],
            'base_room' => ['sometimes', 'in:No,Yes — shared,Yes — dedicated'],
            'move_in' => ['nullable', 'date'],
            'move_out' => ['nullable', 'date'],
            'priority' => ['sometimes', 'in:Low,Medium,High,Critical'],
            'approver_routing' => ['sometimes', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'layout_file' => ['nullable', 'file', 'max:20480'],
        ]);

        if ($request->hasFile('layout_file')) {
            if ($materialRequest->layout_file_path) {
                Storage::disk('public')->delete($materialRequest->layout_file_path);
            }
            $file = $request->file('layout_file');
            $data['layout_file_path'] = $file->store('mp-request-layouts', 'public');
            $data['layout_file_name'] = $file->getClientOriginalName();
        }
        unset($data['layout_file']);

        $materialRequest->update($data);

        return response()->json($this->present($materialRequest));
    }

    public function submit(MaterialRequest $materialRequest)
    {
        Gate::authorize('update', $materialRequest);

        $materialRequest->update(['status' => 'submitted', 'submitted_at' => now()]);

        $materialRequest->activity()->create([
            'actor_user_id' => request()->user()->id,
            'verb' => 'submitted',
            'subject' => 'v1',
            'note' => $materialRequest->items . ' line items · ' . $materialRequest->value,
        ]);

        return response()->json($this->present($materialRequest));
    }

    public function destroy(MaterialRequest $materialRequest)
    {
        Gate::authorize('delete', $materialRequest);

        if ($materialRequest->layout_file_path) {
            Storage::disk('public')->delete($materialRequest->layout_file_path);
        }

        $materialRequest->delete();

        return response()->json(['message' => 'Request deleted successfully.']);
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'codes' => ['required', 'array', 'min:1'],
            'codes.*' => ['string'],
        ]);

        $requests = MaterialRequest::whereIn('code', $data['codes'])->get();

        foreach ($requests as $materialRequest) {
            Gate::authorize('delete', $materialRequest);

            if ($materialRequest->layout_file_path) {
                Storage::disk('public')->delete($materialRequest->layout_file_path);
            }

            $materialRequest->delete();
        }

        return response()->json(['deleted' => $requests->pluck('code')]);
    }

    private function present(MaterialRequest $materialRequest): array
    {
        return [
            'id' => $materialRequest->id,
            'code' => $materialRequest->code,
            'status' => $materialRequest->status,
            'layoutFileName' => $materialRequest->layout_file_name,
            'layoutFileUrl' => $materialRequest->layout_file_path
                ? Storage::disk('public')->url($materialRequest->layout_file_path)
                : null,
        ];
    }

    private function presentDetail(MaterialRequest $materialRequest): array
    {
        return array_merge($this->present($materialRequest), [
            'title' => $materialRequest->title,
            'venue' => $materialRequest->venue?->short_name,
            'venueId' => $materialRequest->venue_id,
            'functionalAreaId' => $materialRequest->functional_area_id,
            'functionalArea' => $materialRequest->functionalArea?->title,
            'site' => $materialRequest->site_name,
            'siteType' => $materialRequest->site_type,
            'siteCode' => $materialRequest->site_code,
            'lsCategory' => $materialRequest->ls_category,
            'lsSub' => $materialRequest->ls_sub,
            'lsName' => $materialRequest->ls_name,
            'lsCode' => $materialRequest->ls_code,
            'baseRoom' => $materialRequest->base_room,
            'moveIn' => optional($materialRequest->move_in)->toDateString(),
            'moveOut' => optional($materialRequest->move_out)->toDateString(),
            'priority' => $materialRequest->priority,
            'approverRouting' => $materialRequest->approver_routing,
            'notes' => $materialRequest->notes,
            'owner' => $materialRequest->owner?->name,
            'submittedAt' => optional($materialRequest->submitted_at)->toDateTimeString(),
            'createdAt' => optional($materialRequest->created_at)->toDateTimeString(),
            'updatedAt' => optional($materialRequest->updated_at)->toDateTimeString(),
            'domain' => $materialRequest->domain,
            'items' => $materialRequest->items,
            'qty' => $materialRequest->qty,
            'value' => $materialRequest->value,
            'lines' => $materialRequest->lines->map(fn (RequestLine $line) => [
                'id' => $line->id,
                'sku' => $line->sku,
                'name' => $line->catalogItem?->name,
                'unit' => $line->unit,
                'domain' => $line->domain,
                'qty' => (int) $line->qty,
                'rate' => (float) $line->rate_snapshot,
                'value' => round($line->qty * $line->rate_snapshot, 2),
                'comment' => $line->comment,
                'serviceOptionId' => $line->service_option_id,
                'serviceOptionName' => $line->serviceOption?->name,
                'supplierName' => $line->serviceOption?->supplier?->name,
            ])->values()->all(),
            'approvalSteps' => $materialRequest->approvalSteps->map(fn (RequestApprovalStep $step) => [
                'id' => $step->id,
                'stepNo' => $step->step_no,
                'role' => $step->role_label,
                'approverName' => $step->approver?->name,
                'state' => $step->state,
                'actedAt' => optional($step->acted_at)->toDateTimeString(),
                'note' => $step->note,
            ])->values()->all(),
            'activity' => $materialRequest->activity->map(fn (RequestActivity $entry) => [
                'id' => $entry->id,
                'actorName' => $entry->actor?->name,
                'verb' => $entry->verb,
                'subject' => $entry->subject,
                'note' => $entry->note,
                'createdAt' => optional($entry->created_at)->toDateTimeString(),
            ])->values()->all(),
            'changeOrders' => $materialRequest->changeOrders->map(fn (ChangeOrder $co) => [
                'id' => $co->id,
                'code' => $co->code,
                'title' => $co->title,
                'context' => $co->context,
                'reason' => $co->reason,
                'raisedByName' => $co->raisedBy?->name,
                'raisedOn' => optional($co->raised_on)->toDateString(),
                'state' => $co->state,
                'stage' => $co->stage,
                'delta' => $co->delta,
                'rows' => $co->rows,
                'lines' => $co->lines->map(fn (ChangeOrderLine $line) => [
                    'id' => $line->id,
                    'sku' => $line->sku,
                    'name' => $line->catalogItem?->name,
                    'qtyBefore' => $line->qty_before,
                    'qtyAfter' => $line->qty_after,
                    'rateBefore' => $line->rate_before,
                    'rateAfter' => $line->rate_after,
                    'dValue' => $line->d_value,
                    'why' => $line->why,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }
}
