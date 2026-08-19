<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\Area;
use App\Models\User;

class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Area $area): bool
    {
        return true;
    }

    /**
     * Areas are organizational groupings, not tied to a material domain —
     * admin-only, same as suppliers.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Area $area): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->hasRole('admin');
    }
}
