<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        if (app()->environment('production')) {
            $this->command?->warn(
                'Skipped demo staff accounts and sample restaurant data in production.',
            );

            return;
        }

        $this->call([
            UserSeeder::class,
            OrderDemoSeeder::class,
        ]);
    }
}
