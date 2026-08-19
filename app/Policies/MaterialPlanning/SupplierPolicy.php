<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return true;
    }

    /**
     * Suppliers aren't domain-scoped — a supplier can serve multiple domains.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasRole('admin');
    }
}
