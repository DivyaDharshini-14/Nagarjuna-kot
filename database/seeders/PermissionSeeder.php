<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'company',
            'branch',
            'kitchen',
            'floor',
            'table',
            'category',
            'menu-type',
            'menu-item',
            'kitchen-mapping',
            'user',
            'role',
            'permission',
            'order',
            'kot',
        ];

        $actions = [
            'view',
            'add',
            'edit',
            'delete',
            'approve',
            'cancel',
            'export',
            'stock-adjustment',
        ];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::updateOrCreate(
                    ['slug' => "{$module}.{$action}"],
                    [
                        'name' => ucfirst(str_replace('-', ' ', $action)),
                        'module' => $module,
                        'description' => ucfirst($action).' '.$module,
                    ]
                );
            }
        }
    }
}
