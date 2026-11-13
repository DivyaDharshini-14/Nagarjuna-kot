<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'UserSeeder creates demo accounts with shared credentials and cannot run in production.',
            );
        }

        // Ensure default company and branch exist
        $company = Company::firstOrCreate(
            ['code' => 'NAGARJUNA'],
            [
                'name' => 'Nagarjuna Restaurant Group',
                'address' => 'Residency Road, Bangalore',
                'phone' => '080-25555555',
                'email' => 'contact@nagarjuna.com',
                'gst_number' => '29AAAAA0000A1Z5',
                'is_active' => true,
            ]
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'IND-01'],
            [
                'company_id' => $company->id,
                'name' => 'Indiranagar Branch',
                'address' => '100ft Road, Indiranagar, Bangalore',
                'phone' => '080-25555556',
                'email' => 'indiranagar@nagarjuna.com',
                'is_active' => true,
            ]
        );

        $adminRole = Role::where('slug', 'admin')->first();
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $waiterRole = Role::where('slug', 'waiter')->first();

        // 1. Admin User
        $admin = User::updateOrCreate(
            ['email' => 'nagarjuna.admin@gmail.com'],
            [
                'name' => 'System Admin',
                'employee_code' => 'EMP-001',
                'designation' => 'System Administrator',
                'password' => Hash::make('password123'),
                'phone' => '9876543210',
                'company_id' => null,
                'branch_id' => null,
                'is_active' => true,
            ]
        );
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }
        if ($superAdminRole) {
            $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }

        // Also keep admin@nagarjuna.com
        $adminWork = User::updateOrCreate(
            ['email' => 'admin@nagarjuna.com'],
            [
                'name' => 'System Admin',
                'employee_code' => 'EMP-001W',
                'designation' => 'System Administrator',
                'password' => Hash::make('password123'),
                'phone' => '9876543210',
                'company_id' => null,
                'branch_id' => null,
                'is_active' => true,
            ]
        );
        if ($adminRole) {
            $adminWork->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // 2. Manager User (assigned to Indiranagar Branch)
        $manager = User::updateOrCreate(
            ['email' => 'nagarjuna.manager@gmail.com'],
            [
                'name' => 'Branch Manager',
                'employee_code' => 'EMP-002',
                'designation' => 'Restaurant Branch Manager',
                'password' => Hash::make('password123'),
                'phone' => '9876543211',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($managerRole) {
            $manager->roles()->sync([$managerRole->id]);
        }

        $managerWork = User::updateOrCreate(
            ['email' => 'manager@nagarjuna.com'],
            [
                'name' => 'Branch Manager',
                'employee_code' => 'EMP-002W',
                'designation' => 'Restaurant Branch Manager',
                'password' => Hash::make('password123'),
                'phone' => '9876543211',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($managerRole) {
            $managerWork->roles()->sync([$managerRole->id]);
        }

        // 3. Waiter User (assigned to Indiranagar Branch)
        $waiter = User::updateOrCreate(
            ['email' => 'nagarjuna.waiter@gmail.com'],
            [
                'name' => 'Ramesh Waiter',
                'employee_code' => 'EMP-003',
                'designation' => 'Senior Captain / Waiter',
                'password' => Hash::make('password123'),
                'phone' => '9876543212',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($waiterRole) {
            $waiter->roles()->sync([$waiterRole->id]);
        }

        $waiterWork = User::updateOrCreate(
            ['email' => 'waiter@nagarjuna.com'],
            [
                'name' => 'Ramesh Waiter',
                'employee_code' => 'EMP-003W',
                'designation' => 'Senior Captain / Waiter',
                'password' => Hash::make('password123'),
                'phone' => '9876543212',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($waiterRole) {
            $waiterWork->roles()->sync([$waiterRole->id]);
        }
        $kitchenStaffRole = Role::where('slug', 'kitchen-staff')->first();

        // 4. Kitchen Staff User (assigned to Indiranagar Branch)
        $kitchenStaff = User::updateOrCreate(
            ['email' => 'nagarjuna.kitchen@gmail.com'],
            [
                'name' => 'Biryani Chef Suresh',
                'employee_code' => 'EMP-004',
                'designation' => 'Executive Kitchen Chef',
                'password' => Hash::make('password123'),
                'phone' => '9876543213',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($kitchenStaffRole) {
            $kitchenStaff->roles()->sync([$kitchenStaffRole->id]);
        }

        $kitchenStaffWork = User::updateOrCreate(
            ['email' => 'kitchen@nagarjuna.com'],
            [
                'name' => 'Kitchen Staff Indiranagar',
                'employee_code' => 'EMP-004W',
                'designation' => 'Kitchen Staff / Cook',
                'password' => Hash::make('password123'),
                'phone' => '9876543213',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($kitchenStaffRole) {
            $kitchenStaffWork->roles()->sync([$kitchenStaffRole->id]);
        }
    }
}
