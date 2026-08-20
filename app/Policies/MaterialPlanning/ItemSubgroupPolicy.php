<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\ItemSubgroup;
use App\Models\User;

class ItemSubgroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ItemSubgroup $itemSubgroup): bool
    {
        return true;
    }

    /**
     * Item subgroups are catalog groupings, not tied to a material domain —
     * admin-only, same as areas/spaces.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, ItemSubgroup $itemSubgroup): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ItemSubgroup $itemSubgroup): bool
    {
        return $user->hasRole('admin');
    }
}
