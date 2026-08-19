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
     */
    public function view(User $user, MaterialRequest $request): bool
    {
        return true;
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
