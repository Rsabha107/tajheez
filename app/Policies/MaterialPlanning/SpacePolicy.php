<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\Space;
use App\Models\User;

class SpacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Space $space): bool
    {
        return true;
    }

    /**
     * Spaces are organizational, not tied to a material domain — admin-only,
     * same as suppliers/areas.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Space $space): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Space $space): bool
    {
        return $user->hasRole('admin');
    }
}
