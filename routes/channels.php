<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('kitchens.{kitchenId}', function (User $user, int $kitchenId) {
    return $user->isKitchenStaff()
        && $user->hasPermission('kot.view')
        && $user->kitchens()
            ->whereKey($kitchenId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->exists();
});
