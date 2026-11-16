<?php

use App\Models\Branch;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('kitchens.{kitchenId}', function (User $user, int $kitchenId) {
    if (! $user->hasPermission('kot.view')) {
        return false;
    }
    $kitchen = Kitchen::query()
        ->whereKey($kitchenId)
        ->where('is_active', true)
        ->whereHas('company', fn ($query) => $query->where('is_active', true))
        ->whereHas('branch', fn ($query) => $query->where('is_active', true)->whereColumn('branches.company_id', 'kitchens.company_id'))
        ->first();
    if (! $kitchen) {
        return false;
    }
    if ($user->isAdmin()) {
        return true;
    }
    if ((int) $user->company_id !== (int) $kitchen->company_id) {
        return false;
    }
    if (! $user->hasRole('company-admin') && (int) $user->branch_id !== (int) $kitchen->branch_id) {
        return false;
    }

    return $user->isKitchenStaff()
        ? $user->kitchens()->whereKey($kitchenId)->exists()
        : $user->isManager() || $user->hasRole('company-admin');
});

Broadcast::channel('branches.{branchId}.menu', function (User $user, int $branchId) {
    return $user->currentAccessToken()?->can('waiter:mobile') === true
        && $user->hasPermission('menu-item.view')
        && (int) $user->branch_id === $branchId
        && Branch::query()
            ->whereKey($branchId)
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->exists();
});

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return $user->currentAccessToken()?->can('waiter:mobile') === true
        && $user->hasPermission('order.view')
        && Order::query()
            ->whereKey($orderId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->exists();
});
