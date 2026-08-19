<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\CatalogItem;
use App\Models\User;

class CatalogItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CatalogItem $item): bool
    {
        return true;
    }

    /**
     * $domainCode is the domain the new item would belong to (from the
     * validated request payload) — passed via Gate::authorize('create', [CatalogItem::class, $domainCode]).
     */
    public function create(User $user, ?string $domainCode = null): bool
    {
        return $user->hasRole('admin') || ($domainCode !== null && $user->managed_domain === $domainCode);
    }

    public function update(User $user, CatalogItem $item): bool
    {
        return $user->hasRole('admin') || $user->managed_domain === $item->domain_code;
    }

    public function delete(User $user, CatalogItem $item): bool
    {
        return $this->update($user, $item);
    }
}
