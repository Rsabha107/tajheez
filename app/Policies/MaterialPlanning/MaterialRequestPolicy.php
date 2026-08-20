<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\User;

class MaterialRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A request can span multiple domains — whole-request visibility is
     * never domain-gated. Only its lines (RequestLinePolicy) are.
     *
     * It IS gated by Functional Area, though: a user only sees requests
     * raised under an FA they're assigned to. Requests with no FA set
     * (legacy data, or FA scoping simply not in use) stay visible to everyone.
     */
    public function view(User $user, MaterialRequest $request): bool
    {
        if ($user->hasRole('admin') || ! $request->functional_area_id) {
            return true;
        }

        return $user->functionalAreas()->where('functional_areas.id', $request->functional_area_id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MaterialRequest $request): bool
    {
        return $user->hasRole('admin') || $request->owner_user_id === $user->id;
    }

    public function delete(User $user, MaterialRequest $request): bool
    {
        return $user->hasRole('admin');
    }
}
