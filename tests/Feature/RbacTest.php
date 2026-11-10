<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Role $adminRole;
    protected Role $managerRole;
    protected Role $waiterRole;
    protected User $adminUser;
    protected User $managerUser;
    protected User $waiterUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->company = Company::create([
            'name' => 'Nagarjuna Group',
            'code' => 'NAG',
            'is_active' => true,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Indiranagar Branch',
            'code' => 'BLR01',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Koramangala Branch',
            'code' => 'BLR02',
            'is_active' => true,
        ]);

        $this->adminRole = Role::where('slug', 'admin')->firstOrFail();
        $this->managerRole = Role::where('slug', 'manager')->firstOrFail();
        $this->waiterRole = Role::where('slug', 'waiter')->firstOrFail();

        $this->adminUser = User::create([
            'name' => 'System Admin',
            'email' => 'admin@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($this->adminRole);

        $this->managerUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Branch Manager A',
            'email' => 'manager.a@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->managerUser->roles()->attach($this->managerRole);

        $this->waiterUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Waiter 1',
            'email' => 'waiter1@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->waiterUser->roles()->attach($this->waiterRole);
    }

    public function test_roles_and_permissions_are_configured_correctly(): void
    {
        $this->assertTrue($this->adminUser->hasRole('admin'));
        $this->assertTrue($this->adminUser->isAdmin());
        $this->assertTrue($this->adminUser->hasPermission('role.view'));
        $this->assertTrue($this->adminUser->hasPermission('user.view'));
        $this->assertTrue($this->adminUser->hasPermission('company.view'));

        $this->assertTrue($this->managerUser->hasRole('manager'));
        $this->assertTrue($this->managerUser->isManager());
        $this->assertTrue($this->managerUser->hasPermission('branch.view'));
        $this->assertTrue($this->managerUser->hasPermission('table.view'));
        $this->assertTrue($this->managerUser->hasPermission('user.view'));
        $this->assertTrue($this->managerUser->hasPermission('user.add'));
        $this->assertTrue($this->managerUser->hasPermission('user.edit'));
        $this->assertFalse($this->managerUser->hasPermission('role.view'));
        $this->assertFalse($this->managerUser->hasPermission('company.add'));

        $this->assertTrue($this->waiterUser->hasRole('waiter'));
        $this->assertTrue($this->waiterUser->isWaiter());
        $this->assertTrue($this->waiterUser->hasPermission('table.view'));
        $this->assertTrue($this->waiterUser->hasPermission('menu-item.view'));
        $this->assertFalse($this->waiterUser->hasPermission('user.view'));
        $this->assertFalse($this->waiterUser->hasPermission('role.view'));
        $this->assertFalse($this->waiterUser->hasPermission('branch.view'));
    }

    public function test_sidebar_displays_menus_based_on_permissions(): void
    {
        // Admin sees Access Control (Users, Roles) and Masters (Companies, Branches, etc.)
        $response = $this->actingAs($this->adminUser)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('Access Control');
        $response->assertSee(route('users.index'));
        $response->assertSee(route('roles.index'));
        $response->assertSee(route('companies.index'));
        $response->assertSee(route('branches.index'));
        $response->assertSee(route('tables.index'));

        // Manager sees Users and permitted Masters, but NOT Roles & Permissions or Companies
        $response = $this->actingAs($this->managerUser)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(route('users.index'));
        $response->assertDontSee(route('roles.index'));
        $response->assertDontSee(route('companies.index'));
        $response->assertSee(route('branches.index'));
        $response->assertSee(route('tables.index'));

        // Waiter sees only Tables, Menu Items, and Orders, but NOT Users, Roles, Branches, or Companies
        $response = $this->actingAs($this->waiterUser)->get('/dashboard');
        $response->assertOk();
        $response->assertDontSee(route('users.index'));
        $response->assertDontSee(route('roles.index'));
        $response->assertDontSee(route('companies.index'));
        $response->assertDontSee(route('branches.index'));
        $response->assertSee(route('orders.index'));
        $response->assertSee(route('tables.index'));
        $response->assertSee(route('menu-items.index'));

        // Kitchen Staff sees Kitchen Operations & KOT Tickets, but NOT Orders, Tables, Users, or Roles
        $kitchenStaffRole = Role::where('slug', 'kitchen-staff')->firstOrFail();
        $kitchenUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Chef Suresh',
            'email' => 'chef@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $kitchenUser->roles()->attach($kitchenStaffRole);

        $response = $this->actingAs($kitchenUser)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('Kitchen Operations');
        $response->assertSee(route('kots.index'));
        $response->assertDontSee(route('orders.index'));
        $response->assertDontSee(route('tables.index'));
        $response->assertDontSee(route('users.index'));
        $response->assertDontSee(route('roles.index'));
    }

    public function test_admin_can_manage_roles_and_permissions(): void
    {
        // Admin can view roles list
        $this->actingAs($this->adminUser)->get('/roles')->assertOk()->assertSee('Roles & Permissions');

        // Admin can create a new role with permissions
        $perm = Permission::where('slug', 'table.view')->firstOrFail();
        $response = $this->actingAs($this->adminUser)->post('/roles', [
            'name' => 'Floor Supervisor',
            'slug' => 'floor-supervisor',
            'description' => 'Supervises tables and floor',
            'permissions' => [$perm->id],
            'is_active' => '1',
        ]);
        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['slug' => 'floor-supervisor']);

        $role = Role::where('slug', 'floor-supervisor')->firstOrFail();
        $this->assertTrue($role->permissions->contains($perm));

        // Core system roles cannot be deleted
        $this->actingAs($this->adminUser)->delete('/roles/' . $this->adminRole->id)->assertForbidden();
    }

    public function test_manager_and_waiter_cannot_access_roles(): void
    {
        $this->actingAs($this->managerUser)->get('/roles')->assertForbidden();
        $this->actingAs($this->waiterUser)->get('/roles')->assertForbidden();
    }

    public function test_admin_can_create_user_and_assign_any_role(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/users', [
            'name' => 'New Branch Admin',
            'email' => 'newadmin@nagarjuna.com',
            'password' => 'secret1234',
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'roles' => [$this->adminRole->id],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'));
        $created = User::where('email', 'newadmin@nagarjuna.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('admin'));
    }

    public function test_manager_can_view_only_authorized_branch_users(): void
    {
        // Another branch user in branch B
        $userBranchB = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Branch B Waiter',
            'email' => 'waiter.b@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $userBranchB->roles()->attach($this->waiterRole);

        $response = $this->actingAs($this->managerUser)->get('/users');
        $response->assertOk();
        $response->assertSee('Waiter 1'); // Branch A user
        $response->assertDontSee('Branch B Waiter'); // Branch B user hidden
        $response->assertDontSee('System Admin'); // Admin account hidden from manager
    }

    public function test_manager_can_create_users_only_for_their_branch_with_non_admin_roles(): void
    {
        // Manager creates a waiter in their branch
        $response = $this->actingAs($this->managerUser)->post('/users', [
            'name' => 'Branch A New Waiter',
            'email' => 'newwaiter@nagarjuna.com',
            'password' => 'secret1234',
            'roles' => [$this->waiterRole->id],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'));
        $created = User::where('email', 'newwaiter@nagarjuna.com')->firstOrFail();
        $this->assertEquals($this->company->id, $created->company_id);
        $this->assertEquals($this->branchA->id, $created->branch_id);
        $this->assertTrue($created->hasRole('waiter'));
    }

    public function test_manager_cannot_assign_admin_role(): void
    {
        $response = $this->actingAs($this->managerUser)->post('/users', [
            'name' => 'Escalated User',
            'email' => 'escalate@nagarjuna.com',
            'password' => 'secret1234',
            'roles' => [$this->adminRole->id],
            'is_active' => '1',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_cannot_edit_or_delete_admin_or_cross_branch_users(): void
    {
        // Cannot edit admin user
        $this->actingAs($this->managerUser)->get('/users/' . $this->adminUser->id . '/edit')->assertForbidden();

        // User from branch B
        $userBranchB = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Branch B Waiter',
            'email' => 'waiter.b2@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $userBranchB->roles()->attach($this->waiterRole);

        // Cannot edit user from branch B
        $this->actingAs($this->managerUser)->get('/users/' . $userBranchB->id . '/edit')->assertForbidden();

        // Manager can edit waiter in their branch A
        $this->actingAs($this->managerUser)->get('/users/' . $this->waiterUser->id . '/edit')->assertOk();
    }

    public function test_manager_cannot_delete_self(): void
    {
        $this->actingAs($this->managerUser)->delete('/users/' . $this->managerUser->id)->assertForbidden();
    }

    public function test_waiter_cannot_access_user_management(): void
    {
        $this->actingAs($this->waiterUser)->get('/users')->assertForbidden();
        $this->actingAs($this->waiterUser)->get('/users/create')->assertForbidden();
    }

    public function test_branch_level_restrictions_enforced_on_restaurant_tables(): void
    {
        $floorB = \App\Models\Floor::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'name' => 'First Floor B',
            'code' => 'FL-B1',
            'is_active' => true,
        ]);

        // Create table in branch B
        $tableB = RestaurantTable::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'floor_id' => $floorB->id,
            'name' => 'Table B-1',
            'seating_capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        // Manager A cannot edit or delete table in Branch B
        $this->actingAs($this->managerUser)->get('/tables/' . $tableB->id . '/edit')->assertForbidden();
        $this->actingAs($this->managerUser)->delete('/tables/' . $tableB->id)->assertForbidden();

        // Admin can edit table in Branch B
        $this->actingAs($this->adminUser)->get('/tables/' . $tableB->id . '/edit')->assertOk();
    }

    public function test_manager_can_access_permitted_create_pages(): void
    {
        $this->actingAs($this->managerUser)->get('/menu-items/create')->assertOk();
        $this->actingAs($this->managerUser)->get('/tables/create')->assertOk();
        $this->actingAs($this->managerUser)->get('/floors/create')->assertOk();
        $this->actingAs($this->managerUser)->get('/kitchens/create')->assertOk();
        $this->actingAs($this->managerUser)->get('/categories/create')->assertOk();
        $this->actingAs($this->managerUser)->get('/menu-types/create')->assertOk();
        $this->actingAs($this->managerUser)->get('/kitchen-mappings/create')->assertOk();
        $this->actingAs($this->managerUser)->get('/users/create')->assertOk();

        // Option endpoints
        $this->actingAs($this->managerUser)->getJson(route('branches.options', ['company_id' => $this->company->id]))->assertOk();
        $this->actingAs($this->managerUser)->getJson(route('kitchens.options', ['branch_id' => $this->branchA->id]))->assertOk();
        $this->actingAs($this->managerUser)->getJson(route('floors.options', ['branch_id' => $this->branchA->id]))->assertOk();
        $this->actingAs($this->managerUser)->getJson(route('categories.options', ['company_id' => $this->company->id]))->assertOk();
        $this->actingAs($this->managerUser)->getJson(route('menu-types.options', ['company_id' => $this->company->id]))->assertOk();
        $this->actingAs($this->managerUser)->getJson(route('menu-items.options', ['branch_id' => $this->branchA->id]))->assertOk();
    }
}
