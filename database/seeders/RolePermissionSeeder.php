<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::pluck('id')->toArray();

        $managerPermissions = Permission::whereIn('slug', [
            'branch.view', 'branch.edit',
            'kitchen.view', 'kitchen.add', 'kitchen.edit',
            'floor.view', 'floor.add', 'floor.edit',
            'table.view', 'table.add', 'table.edit',
            'category.view', 'category.add', 'category.edit',
            'menu-type.view', 'menu-type.add', 'menu-type.edit',
            'menu-item.view', 'menu-item.add', 'menu-item.edit',
            'kitchen-mapping.view', 'kitchen-mapping.add', 'kitchen-mapping.edit',
            'user.view', 'user.add', 'user.edit',
            'order.view', 'order.add', 'order.edit', 'order.cancel', 'order.approve',
            'kot.view', 'kot.edit',
            'menu-item.stock-adjustment',
        ])->pluck('id')->toArray();

        $waiterPermissions = Permission::whereIn('slug', [
            'order.view',
            'order.add',
            'order.edit',
            'order.cancel',
            'table.view',
            'floor.view',
            'category.view',
            'menu-type.view',
            'menu-item.view',
        ])->pluck('id')->toArray();

        $kitchenStaffPermissions = Permission::whereIn('slug', [
            'kitchen.view',
            'kot.view',
            'kot.edit',
            'menu-item.view',
        ])->pluck('id')->toArray();

        $kitchenManagerPermissions = array_values(array_unique(array_merge(
            $kitchenStaffPermissions,
            Permission::whereIn('slug', ['menu-item.stock-adjustment'])->pluck('id')->toArray(),
        )));

        $permissions = [
            // Admin – full access including managing roles and permissions
            'admin' => $allPermissions,
            'super-admin' => $allPermissions,

            // Company Admin – manage company-level setup
            'company-admin' => Permission::whereIn('module', [
                'company', 'branch', 'kitchen', 'floor', 'table',
                'category', 'menu-type', 'menu-item', 'kitchen-mapping', 'user',
            ])->pluck('id')->toArray(),

            // Manager – manage branch operations, staff, order and KOT monitoring
            'manager' => $managerPermissions,
            'branch-manager' => $managerPermissions,

            // Waiter – order management, table and floor service
            'waiter' => $waiterPermissions,
            'captain-waiter' => $waiterPermissions,

            // Kitchen Staff – kitchen display & KOT status actions
            'kitchen-manager' => $kitchenManagerPermissions,
            'kitchen-staff' => $kitchenStaffPermissions,

            // Restaurant Manager – view access
            'restaurant-manager' => Permission::whereIn('slug', [
                'branch.view',
                'kitchen.view',
                'floor.view',
                'table.view',
                'category.view',
                'menu-type.view',
                'menu-item.view',
                'kitchen-mapping.view',
                'user.view',
            ])->pluck('id')->toArray(),

        ];

        foreach ($permissions as $roleSlug => $permissionIds) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();

            $role->permissions()->sync($permissionIds);
        }
    }
}
