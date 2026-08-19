<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\MaterialPlanning\ChangeOrderLine;
use App\Models\User;

class ChangeOrderLinePolicy
{
    public function createForChangeOrder(User $user, ChangeOrder $changeOrder, CatalogItem $item): bool
    {
        return $user->hasRole('admin') || $user->managed_domain === $item->domain_code;
    }

    public function update(User $user, ChangeOrderLine $line): bool
    {
        return $user->hasRole('admin') || $user->managed_domain === $line->catalogItem?->domain_code;
    }

    public function delete(User $user, ChangeOrderLine $line): bool
    {
        return $this->update($user, $line);
    }
}
