<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\ItemSubgroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemSubgroupController extends Controller
{
    public function data()
    {
        $itemSubgroups = ItemSubgroup::with(['group', 'status'])->orderBy('name')->get();

        return response()->json($itemSubgroups->map(fn (ItemSubgroup $s) => $this->present($s)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', ItemSubgroup::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:mp_item_subgroups,code'],
            'group_id' => ['required', 'exists:mp_item_groups,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);
        $data['status_id'] = $data['status_id'] ?? 1;

        $itemSubgroup = ItemSubgroup::create($data);

        return response()->json($this->present($itemSubgroup->load('group', 'status')), 201);
    }

    public function update(Request $request, ItemSubgroup $itemSubgroup)
    {
        Gate::authorize('update', $itemSubgroup);

        $data = $request->validate([
            'group_id' => ['sometimes', 'exists:mp_item_groups,id'],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);

        $itemSubgroup->update($data);

        return response()->json($this->present($itemSubgroup->load('group', 'status')));
    }

    public function destroy(ItemSubgroup $itemSubgroup)
    {
        Gate::authorize('delete', $itemSubgroup);

        $itemSubgroup->delete();

        return response()->json(['message' => 'Item subgroup deleted successfully.']);
    }

    private function present(ItemSubgroup $itemSubgroup): array
    {
        return [
            'id' => $itemSubgroup->id,
            'code' => $itemSubgroup->code,
            'name' => $itemSubgroup->name,
            'description' => $itemSubgroup->description,
            'group' => $itemSubgroup->group_id,
            'groupLabel' => $itemSubgroup->group?->label,
            'statusId' => $itemSubgroup->status_id,
            'statusName' => $itemSubgroup->status?->name,
            'statusColor' => $itemSubgroup->status?->color,
        ];
    }
}
