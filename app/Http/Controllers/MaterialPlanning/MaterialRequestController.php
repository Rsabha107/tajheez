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
use Illuminate\Validation\Rule;

class MaterialRequestController extends Controller
{
    /**
     * Requests + their flattened line items — moved out of the initial
     * material-planning.index Inertia payload so this (the heaviest,
     * most-frequently-changing dataset) is fetched over its own endpoint
     * instead of being shipped on every page load.
     */
    public function data()
    {
        $user = auth()->user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        $userFunctionalAreaIds = $user?->functionalAreas()->pluck('functional_areas.id')->all() ?? [];

        $requests = MaterialRequest::with([
            'venue', 'owner', 'functionalArea', 'space', 'area',
            'lines.catalogItem', 'lines.serviceOption.services.supplier',
        ])
            ->when(! $isAdmin, fn ($q) => $q->whereIn('functional_area_id', $userFunctionalAreaIds))
            ->orderByDesc('id')->get();

        return response()->json([
            'requests' => $requests->map(fn (MaterialRequest $r) => [
                'id' => $r->code,
                'dbId' => $r->id,
                'eventId' => $r->event_id,
                'title' => $r->title,
                'venue' => $r->venue?->short_name,
                'functionalArea' => $r->functionalArea?->fa_code,
                'site' => $r->site_name,
                'domain' => $r->domain,
                'status' => $r->status,
                'items' => $r->items,
                'qty' => $r->qty,
                'value' => $r->value,
                'submitted' => $r->submitted,
                'updated' => $r->updated,
                'owner' => $r->owner?->initials,
                'priority' => $r->priority,
                'space' => $r->space_id,
                'spaceLabel' => $r->space?->name,
                'area' => $r->area_id,
                'areaLabel' => $r->area?->label,
                'hasServiceOption' => $r->lines->contains(fn ($l) => $l->service_option_id !== null),
            ])->values()->all(),

            'requestLines' => $requests->flatMap(fn (MaterialRequest $r) => $r->lines->map(fn (RequestLine $l) => [
                'id' => $l->id,
                'requestId' => $r->code,
                'requestTitle' => $r->title,
                'eventId' => $l->event_id,
                'status' => $r->status,
                'sku' => $l->sku,
                'name' => $l->catalogItem?->name,
                'domain' => $l->domain,
                'group' => $l->catalogItem?->group,
                'sub' => $l->catalogItem?->sub,
                'venue' => $r->venue?->short_name,
                'functionalArea' => $r->functionalArea?->fa_code,
                'ownerId' => $r->owner_user_id,
                'ownerInitials' => $r->owner?->initials,
                'space' => $r->space_id,
                'spaceLabel' => $r->space?->name,
                'area' => $r->area_id,
                'areaLabel' => $r->area?->label,
                'moveIn' => $r->move_in?->format('Y-m-d'),
                'moveOut' => $r->move_out?->format('Y-m-d'),
                'qty' => $l->qty,
                'unit' => $l->unit,
                'rate' => (float) $l->rate_snapshot,
                'value' => round($l->qty * $l->rate_snapshot, 2),
                'comment' => $l->comment,
                'serviceOptionId' => $l->service_option_id,
                'serviceOptionName' => $l->serviceOption?->name,
                'supplierName' => $l->serviceOption?->services->pluck('supplier.name')->unique()->filter()->implode(', '),
            ]))->values()->all(),
        ]);
    }

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
            'lines.catalogItem', 'lines.serviceOption.services.supplier',
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
            'area_id' => ['nullable', Rule::exists('mp_areas', 'id')->where('event_id', $request->input('event_id'))],
            'space_id' => ['nullable', Rule::exists('mp_spaces', 'id')->where('event_id', $request->input('event_id'))],
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

        $eventId = $request->input('event_id', $materialRequest->event_id);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'event_id' => ['sometimes', 'exists:events,id'],
            'venue_id' => ['sometimes', 'exists:venues,id'],
            'functional_area_id' => ['nullable', 'exists:functional_areas,id'],
            'area_id' => ['nullable', Rule::exists('mp_areas', 'id')->where('event_id', $eventId)],
            'space_id' => ['nullable', Rule::exists('mp_spaces', 'id')->where('event_id', $eventId)],
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

        // mp_request_lines.event_id is a denormalized copy (for direct
        // analysis queries) of the parent request's event — keep it in sync
        // on the rare path where a request's own event is reassigned.
        if (array_key_exists('event_id', $data)) {
            $materialRequest->lines()->update(['event_id' => $materialRequest->event_id]);
        }

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
            'areaId' => $materialRequest->area_id,
            'spaceId' => $materialRequest->space_id,
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
                'sub' => $line->catalogItem?->sub,
                'unit' => $line->unit,
                'domain' => $line->domain,
                'qty' => (int) $line->qty,
                'rate' => (float) $line->rate_snapshot,
                'value' => round($line->qty * $line->rate_snapshot, 2),
                'comment' => $line->comment,
                'serviceOptionId' => $line->service_option_id,
                'serviceOptionName' => $line->serviceOption?->name,
                // A bundle's services can each have their own supplier, so this is a summary list.
                'supplierName' => $line->serviceOption?->services->pluck('supplier.name')->unique()->filter()->implode(', '),
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
