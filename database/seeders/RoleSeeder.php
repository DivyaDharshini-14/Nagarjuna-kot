<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'System Administrator with full access'],
            ['name' => 'Manager', 'slug' => 'manager', 'description' => 'Branch operations manager'],
            ['name' => 'Waiter', 'slug' => 'waiter', 'description' => 'Restaurant waiter / captain'],
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Super Administrator with full access'],
            ['name' => 'Company Admin', 'slug' => 'company-admin', 'description' => 'Company administrator'],
            ['name' => 'Branch Manager', 'slug' => 'branch-manager', 'description' => 'Branch operations manager'],
            ['name' => 'Restaurant Manager', 'slug' => 'restaurant-manager', 'description' => 'Restaurant floor manager'],
            ['name' => 'Captain / Waiter', 'slug' => 'captain-waiter', 'description' => 'Restaurant captain / waiter'],
            ['name' => 'Kitchen Manager', 'slug' => 'kitchen-manager', 'description' => 'Kitchen operations manager'],
            ['name' => 'Kitchen Staff', 'slug' => 'kitchen-staff', 'description' => 'Kitchen staff member'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [...$role, 'is_active' => true]
            );
        }
    }
}
