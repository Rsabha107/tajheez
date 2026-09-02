<?php

namespace App\Http\Controllers\MaterialPlanning;

use App\Http\Controllers\Controller;
use App\Models\MaterialPlanning\ItemGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ItemGroupController extends Controller
{
    public function data()
    {
        $itemGroups = ItemGroup::with(['domain', 'status'])->withCount('subgroups')->orderBy('sort_order')->get();

        return response()->json($itemGroups->map(fn (ItemGroup $g) => $this->present($g)));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', ItemGroup::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', 'unique:mp_item_groups,code'],
            'event_id' => ['required', 'exists:events,id'],
            'domain_id' => ['required', Rule::exists('mp_domains', 'id')->where('event_id', $request->input('event_id'))],
            'label' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status_id'] = $data['status_id'] ?? 1;

        $itemGroup = ItemGroup::create($data);

        return response()->json($this->present($itemGroup->load('domain', 'status')), 201);
    }

    public function update(Request $request, ItemGroup $itemGroup)
    {
        Gate::authorize('update', $itemGroup);

        $eventId = $request->input('event_id', $itemGroup->event_id);

        $data = $request->validate([
            'event_id' => ['sometimes', 'exists:events,id'],
            'domain_id' => ['sometimes', Rule::exists('mp_domains', 'id')->where('event_id', $eventId)],
            'label' => ['sometimes', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status_id' => ['sometimes', 'exists:global_statuses,id'],
        ]);

        $itemGroup->update($data);

        return response()->json($this->present($itemGroup->load('domain', 'status')));
    }

    public function destroy(ItemGroup $itemGroup)
    {
        Gate::authorize('delete', $itemGroup);

        $itemGroup->delete();

        return response()->json(['message' => 'Item group deleted successfully.']);
    }

    private function present(ItemGroup $itemGroup): array
    {
        return [
            'id' => $itemGroup->id,
            'code' => $itemGroup->code,
            'eventId' => $itemGroup->event_id,
            'domain' => $itemGroup->domain_id,
            'domainLabel' => $itemGroup->domain?->label,
            'label' => $itemGroup->label,
            'description' => $itemGroup->description,
            'sortOrder' => $itemGroup->sort_order,
            'subgroupsCount' => $itemGroup->subgroups_count ?? $itemGroup->subgroups()->count(),
            'statusId' => $itemGroup->status_id,
            'statusName' => $itemGroup->status?->name,
            'statusColor' => $itemGroup->status?->color,
        ];
    }
}
