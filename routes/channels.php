<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Order;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('kitchens.{kitchenId}', function (User $user, int $kitchenId) {
    if (! $user->hasPermission('kot.view')) return false;
    $kitchen = \App\Models\Kitchen::query()
        ->whereKey($kitchenId)
        ->where('is_active', true)
        ->first();
    if (! $kitchen) return false;
    if ($user->company_id && (int) $user->company_id !== (int) $kitchen->company_id) return false;
    if ($user->branch_id && (int) $user->branch_id !== (int) $kitchen->branch_id) return false;
    return $user->isKitchenStaff()
        ? $user->kitchens()->whereKey($kitchenId)->exists()
        : $user->isManager() || $user->isAdmin();
});

Broadcast::channel('branches.{branchId}.menu', function (User $user, int $branchId) {
    return $user->currentAccessToken()?->can('waiter:mobile') === true
        && $user->isWaiter()
        && $user->hasPermission('menu-item.view')
        && (int) $user->branch_id === $branchId
        && \App\Models\Branch::query()
            ->whereKey($branchId)
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->exists();
});

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return $user->currentAccessToken()?->can('waiter:mobile') === true
        && $user->isWaiter()
        && $user->hasPermission('order.view')
        && Order::query()
            ->whereKey($orderId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->exists();
});
