<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\MaterialPlanning\MaterialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChangeOrderController extends Controller
{
    public function store(Request $request)
    {
        Gate::authorize('create', ChangeOrder::class);

        $data = $request->validate([
            'request_id' => ['required', 'exists:mp_requests,id'],
            'context' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'in:Scope increase,Scope reduction,Overlay revision,Client request,Service option change,Budget correction'],
        ]);

        MaterialRequest::findOrFail($data['request_id']);

        $changeOrder = ChangeOrder::create([
            ...$data,
            'raised_by_user_id' => $request->user()->id,
            'raised_on' => now()->toDateString(),
            'state' => 'draft',
            'stage' => 'Not submitted',
        ]);

        // `id` is the human code used for display; `dbId` is the real PK the
        // frontend needs for the follow-up change-order-lines.store calls.
        return response()->json(['id' => $changeOrder->code, 'dbId' => $changeOrder->id], 201);
    }

    public function update(Request $request, ChangeOrder $changeOrder)
    {
        Gate::authorize('update', $changeOrder);

        $data = $request->validate([
            'state' => ['sometimes', 'in:draft,pending,approved,rejected'],
            'stage' => ['sometimes', 'string', 'max:40'],
        ]);

        $changeOrder->update($data);

        return response()->json(['id' => $changeOrder->code, 'state' => $changeOrder->state]);
    }

    public function destroy(ChangeOrder $changeOrder)
    {
        Gate::authorize('delete', $changeOrder);

        $changeOrder->delete();

        return response()->json(['message' => 'Change order deleted successfully.']);
    }
}
