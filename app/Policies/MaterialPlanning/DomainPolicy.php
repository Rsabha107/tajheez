<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\Domain;
use App\Models\User;

class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Domain $domain): bool
    {
        return true;
    }

    /**
     * Domains are top-level material categories that item groups, catalog
     * items and category leads all key off of — admin-only, same as
     * areas/spaces.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Domain $domain): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $user->hasRole('admin');
    }
}
