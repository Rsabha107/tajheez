<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\ServiceOptionItem;
use App\Models\User;

class ServiceOptionItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceOptionItem $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, ServiceOptionItem $item): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ServiceOptionItem $item): bool
    {
        return $this->update($user, $item);
    }
}
