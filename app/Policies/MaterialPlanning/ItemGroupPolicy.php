<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\ItemGroup;
use App\Models\User;

class ItemGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ItemGroup $itemGroup): bool
    {
        return true;
    }

    /**
     * Item groups are catalog groupings, not tied to a material domain —
     * admin-only, same as areas/spaces.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, ItemGroup $itemGroup): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ItemGroup $itemGroup): bool
    {
        return $user->hasRole('admin');
    }
}
