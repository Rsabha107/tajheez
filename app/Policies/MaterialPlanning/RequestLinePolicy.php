<?php

namespace App\Policies\MaterialPlanning;

use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\MaterialPlanning\RequestLine;
use App\Models\User;

class RequestLinePolicy
{
    /**
     * No instance exists yet — check against the catalog item the line would add.
     * The parent request itself is never domain-gated (see MaterialRequestPolicy).
     */
    public function createForRequest(User $user, MaterialRequest $request, CatalogItem $item): bool
    {
        return $user->hasRole('admin') || $user->managed_domain === $item->domain_code;
    }

    public function update(User $user, RequestLine $line): bool
    {
        return $user->hasRole('admin') || $user->managed_domain === $line->domain;
    }

    public function delete(User $user, RequestLine $line): bool
    {
        return $this->update($user, $line);
    }
}
