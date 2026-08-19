<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\MaterialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MaterialRequestController extends Controller
{
    public function store(Request $request)
    {
        Gate::authorize('create', MaterialRequest::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'event_id' => ['required', 'exists:events,id'],
            'venue_id' => ['required', 'exists:venues,id'],
            'site_type' => ['nullable', 'string', 'max:80'],
            'site_code' => ['nullable', 'string', 'max:40'],
            'site_name' => ['required', 'string', 'max:120'],
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
        ]);

        $data['owner_user_id'] = $request->user()->id;
        $data['status'] = 'draft';

        $materialRequest = MaterialRequest::create($data);

        return response()->json(['id' => $materialRequest->code], 201);
    }

    public function update(Request $request, MaterialRequest $materialRequest)
    {
        Gate::authorize('update', $materialRequest);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'site_name' => ['sometimes', 'string', 'max:120'],
            'priority' => ['sometimes', 'in:Low,Medium,High,Critical'],
            'approver_routing' => ['sometimes', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ]);

        $materialRequest->update($data);

        return response()->json(['id' => $materialRequest->code]);
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

        return response()->json(['id' => $materialRequest->code, 'status' => $materialRequest->status]);
    }

    public function destroy(MaterialRequest $materialRequest)
    {
        Gate::authorize('delete', $materialRequest);

        $materialRequest->delete();

        return response()->json(['message' => 'Request deleted successfully.']);
    }
}
