<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\CatalogItem;
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
     * No instance exists yet — check against the catalog item the option would belong to.
     */
    public function createForItem(User $user, CatalogItem $item): bool
    {
        return $user->hasRole('admin') || $user->managed_domain === $item->domain_code;
    }

    public function update(User $user, ServiceOption $option): bool
    {
        return $user->hasRole('admin') || $user->managed_domain === $option->domain;
    }

    public function delete(User $user, ServiceOption $option): bool
    {
        return $this->update($user, $option);
    }
}
