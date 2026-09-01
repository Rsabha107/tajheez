<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\ServiceOption;
use App\Models\User;

class ServiceOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceOption $option): bool
    {
        return true;
    }

    /**
     * Service option bundles are no longer scoped to a catalog item's domain,
     * so bundle management (unlike assigning one to a request line) is admin-only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, ServiceOption $option): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ServiceOption $option): bool
    {
        return $this->update($user, $option);
    }
}
