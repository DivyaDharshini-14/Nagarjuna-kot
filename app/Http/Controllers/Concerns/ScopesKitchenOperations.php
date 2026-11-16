<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Kitchen;
use App\Models\KotTicket;
use Illuminate\Database\Eloquent\Builder;

trait ScopesKitchenOperations
{
    protected function accessibleKitchens(): Builder
    {
        $query = Kitchen::query()->where('is_active', true)
            ->whereHas('company', fn ($company) => $company->where('is_active', true))
            ->whereHas('branch', fn ($branch) => $branch->where('is_active', true)
                ->whereColumn('branches.company_id', 'kitchens.company_id'));
        $this->applyBranchScope($query);

        if ($this->actor()->isKitchenStaff() && ! $this->isSuperAdmin()) {
            $query->whereHas('users', fn ($users) => $users->where('users.id', $this->actor()->id));
        }

        return $query;
    }

    protected function scopeKitchenTickets(Builder $query): Builder
    {
        $this->applyBranchScope($query);

        return $query->whereIn('kitchen_id', $this->accessibleKitchens()->select('kitchens.id'));
    }

    protected function authorizeKitchenAccess(Kitchen $kitchen): void
    {
        abort_unless($this->accessibleKitchens()->whereKey($kitchen->id)->exists(), 403);
    }

    protected function authorizeKotAccess(KotTicket $kot): void
    {
        abort_unless($this->scopeKitchenTickets(KotTicket::query())->whereKey($kot->id)->exists(), 403);
    }
}
