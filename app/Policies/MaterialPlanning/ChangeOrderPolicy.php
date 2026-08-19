<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\User;

class ChangeOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChangeOrder $changeOrder): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->hasRole('admin') || $changeOrder->raised_by_user_id === $user->id;
    }

    public function delete(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->hasRole('admin');
    }
}
