<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockTransaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileKitchenStockTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private Kitchen $kitchen;
    private Category $category;
    private MenuType $menuType;
    private User $staff;
    private int $dishSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $this->company = Company::create(['name' => 'Stock Test Company', 'code' => 'STC', 'is_active' => true]);
        $this->branch = Branch::create([
            'company_id' => $this->company->id, 'name' => 'Stock Branch', 'code' => 'STB', 'is_active' => true,
        ]);
        $this->kitchen = $this->makeKitchen($this->branch, 'Assigned');
        $this->category = Category::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => 'Prepared Dishes', 'code' => 'PD', 'is_active' => true,
        ]);
        $this->menuType = MenuType::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => 'Veg', 'is_active' => true,
        ]);
        $this->staff = $this->makeStaff('kitchen-staff');
    }

    public function test_login_and_restored_session_expose_stock_view_permission_without_edit_access(): void
    {
        $dish = $this->makeDish();
        $this->recordMovement($dish, 1);
        $login = $this->postJson('/api/v1/kitchen-mobile/auth/login', [
            'email' => $this->staff->email, 'password' => 'stock-test-password', 'device_name' => 'Stock Test Android',
        ])->assertOk()
            ->assertJsonPath('data.user.can_view_stock', true)
            ->assertJsonPath('data.user.can_manage_stock', false);

        $this->withToken($login->json('data.token'));
        $this->getJson('/api/v1/kitchen-mobile/auth/me')->assertOk()
            ->assertJsonPath('data.user.can_view_stock', true)
            ->assertJsonPath('data.user.can_manage_stock', false);
        $this->getJson('/api/v1/kitchen-mobile/stock')->assertOk()->assertJsonPath('data.items.0.id', $dish->id);
        $this->getJson($this->historyUrl($dish))->assertOk()->assertJsonCount(1, 'data.transactions');
        $this->postJson($this->adjustUrl($dish), [
            'type' => 'stock_in', 'quantity' => 3, 'reason' => 'New batch',
        ])->assertForbidden();
        $this->patchJson($this->availabilityUrl($dish), [
            'availability' => 'sold_out', 'reason' => 'Last portions reserved',
        ])->assertForbidden();
        $this->assertSame(20, $dish->fresh()->prepared_stock_quantity);
        $this->assertDatabaseCount('stock_transactions', 1);
    }

    public function test_staff_without_stock_view_permission_cannot_read_stock_or_history(): void
    {
        Role::where('slug', 'kitchen-staff')->firstOrFail()->permissions()
            ->detach(Permission::where('slug', 'menu-item.view')->value('id'));
        $dish = $this->makeDish();
        $login = $this->postJson('/api/v1/kitchen-mobile/auth/login', [
            'email' => $this->staff->email, 'password' => 'stock-test-password', 'device_name' => 'Stock Test Android',
        ])->assertOk()
            ->assertJsonPath('data.user.can_view_stock', false)
            ->assertJsonPath('data.user.can_manage_stock', false);
        $this->withToken($login->json('data.token'));
        $this->getJson('/api/v1/kitchen-mobile/stock')->assertForbidden();
        $this->getJson($this->historyUrl($dish))->assertForbidden();
    }

    public function test_all_stock_history_pages_are_accessible_in_stable_order(): void
    {
        Sanctum::actingAs($this->staff, ['kitchen:mobile']);
        $dish = $this->makeDish();
        $ids = [];
        for ($index = 1; $index <= 105; $index++) {
            $ids[] = $this->recordMovement($dish, $index)->id;
        }
        $expected = array_reverse($ids);
        $received = [];
        foreach ([1 => 50, 2 => 50, 3 => 5] as $page => $count) {
            $response = $this->getJson($this->historyUrl($dish).'?page='.$page)->assertOk()
                ->assertJsonCount($count, 'data.transactions')
                ->assertJsonPath('data.pagination.current_page', $page)
                ->assertJsonPath('data.pagination.last_page', 3)
                ->assertJsonPath('data.pagination.per_page', 50)
                ->assertJsonPath('data.pagination.total', 105);
            array_push($received, ...array_column($response->json('data.transactions'), 'id'));
        }
        $this->assertSame($expected, $received, 'Equal timestamps must use descending IDs to avoid missing or repeated records.');
        $this->getJson($this->historyUrl($dish).'?page=4')->assertOk()->assertJsonCount(0, 'data.transactions');
    }

    public function test_stock_dish_pages_preserve_assigned_kitchen_scope(): void
    {
        Sanctum::actingAs($this->staff, ['kitchen:mobile']);
        $expectedIds = [];
        for ($index = 1; $index <= 55; $index++) {
            $expectedIds[] = $this->makeDish(['name' => sprintf('Assigned dish %03d', $index)])->id;
        }
        $hidden = $this->makeDish(['name' => 'Unassigned dish'], $this->makeKitchen($this->branch, 'Unassigned'));
        $first = $this->getJson('/api/v1/kitchen-mobile/stock?page=1')->assertOk()
            ->assertJsonCount(50, 'data.items')->assertJsonPath('data.pagination.total', 55)
            ->assertJsonPath('data.pagination.last_page', 2);
        $second = $this->getJson('/api/v1/kitchen-mobile/stock?page=2')->assertOk()
            ->assertJsonCount(5, 'data.items')->assertJsonPath('data.pagination.current_page', 2);
        $actualIds = array_merge(array_column($first->json('data.items'), 'id'), array_column($second->json('data.items'), 'id'));
        $this->assertSame($expectedIds, $actualIds);
        $this->assertNotContains($hidden->id, $actualIds);
    }

    public function test_history_excludes_other_dishes_tenants_and_unassigned_kitchens(): void
    {
        Sanctum::actingAs($this->staff, ['kitchen:mobile']);
        $dish = $this->makeDish();
        $ownMovement = $this->recordMovement($dish, 1);
        $otherDish = $this->makeDish();
        $this->recordMovement($otherDish, 2);
        $unassignedDish = $this->makeDish([], $this->makeKitchen($this->branch, 'Unassigned'));
        $this->recordMovement($unassignedDish, 3);
        $otherCompany = Company::create(['name' => 'Other Company', 'code' => 'OTHER', 'is_active' => true]);
        $otherBranch = Branch::create([
            'company_id' => $otherCompany->id, 'name' => 'Other Branch', 'code' => 'OTHER', 'is_active' => true,
        ]);
        $foreignDish = $this->makeDish([
            'company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id,
        ], $this->makeKitchen($otherBranch, 'Other'));
        $this->recordMovement($foreignDish, 4);
        $this->recordMovement($dish, 5, ['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id]);

        $this->getJson($this->historyUrl($dish))->assertOk()->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.transactions.0.id', $ownMovement->id)
            ->assertJsonPath('data.pagination.total', 1);
        $this->getJson($this->historyUrl($unassignedDish))->assertNotFound();
        $this->getJson($this->historyUrl($foreignDish))->assertNotFound();
        $this->getJson('/api/v1/kitchen-mobile/stock')->assertOk()->assertJsonCount(2, 'data.items');
    }

    public function test_stock_pages_reject_invalid_page_parameters(): void
    {
        Sanctum::actingAs($this->staff, ['kitchen:mobile']);
        $dish = $this->makeDish();
        foreach (['0', '-1', 'word', '1.5'] as $page) {
            $this->getJson('/api/v1/kitchen-mobile/stock?page='.$page)->assertUnprocessable()->assertJsonValidationErrors('page');
            $this->getJson($this->historyUrl($dish).'?page='.$page)->assertUnprocessable()->assertJsonValidationErrors('page');
        }
    }

    public function test_stock_adjustments_record_opening_stock_and_each_change_with_actor_and_reason(): void
    {
        $manager = $this->makeStaff('kitchen-manager');
        Sanctum::actingAs($manager, ['kitchen:mobile']);
        $dish = $this->makeDish(['prepared_stock_quantity' => null]);
        $steps = [
            ['stock_in', 10, null, 'opening_stock', 0, 10],
            ['stock_in', 5, null, 'stock_in', 10, 15],
            ['wastage', 2, null, 'wastage', 15, 13],
            ['damaged', 1, null, 'damaged', 13, 12],
            ['manual_adjustment', 4, 'increase', 'manual_adjustment', 12, 16],
            ['manual_adjustment', 6, 'decrease', 'manual_adjustment', 16, 10],
        ];
        foreach ($steps as $index => [$type, $quantity, $direction, $storedType, $before, $after]) {
            $reason = 'Verified stock movement '.($index + 1);
            $this->postJson($this->adjustUrl($dish), array_filter([
                'type' => $type, 'quantity' => $quantity, 'direction' => $direction, 'reason' => $reason,
            ], fn ($value) => $value !== null))->assertOk()->assertJsonPath('data.item.prepared_stock_quantity', $after);
            $this->assertDatabaseHas('stock_transactions', [
                'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
                'menu_item_id' => $dish->id, 'user_id' => $manager->id, 'transaction_type' => $storedType,
                'quantity' => $quantity, 'quantity_delta' => $after - $before,
                'stock_before' => $before, 'stock_after' => $after, 'reason' => $reason,
            ]);
        }
        $this->getJson($this->historyUrl($dish))->assertOk()->assertJsonCount(6, 'data.transactions')
            ->assertJsonPath('data.transactions.0.user', $manager->name);
        $this->assertNotNull(StockTransaction::latest('id')->firstOrFail()->created_at);
    }

    public function test_adjustments_require_reason_tracking_and_nonnegative_stock(): void
    {
        Sanctum::actingAs($this->makeStaff('kitchen-manager'), ['kitchen:mobile']);
        $dish = $this->makeDish(['prepared_stock_quantity' => 2]);
        $this->postJson($this->adjustUrl($dish), ['type' => 'stock_in', 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->adjustUrl($dish), ['type' => 'stock_in', 'quantity' => 1, 'reason' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        foreach (['wastage', 'damaged', 'manual_adjustment'] as $type) {
            $this->postJson($this->adjustUrl($dish), [
                'type' => $type, 'quantity' => 3, 'direction' => 'decrease', 'reason' => 'Physical count verification',
            ])->assertUnprocessable();
        }
        $disabled = $this->makeDish(['stock_tracking_enabled' => false]);
        $this->postJson($this->adjustUrl($disabled), [
            'type' => 'stock_in', 'quantity' => 1, 'reason' => 'Fresh batch received',
        ])->assertUnprocessable();
        $this->assertSame(2, $dish->fresh()->prepared_stock_quantity);
        $this->assertDatabaseCount('stock_transactions', 0);
    }

    public function test_availability_changes_require_reason_and_record_audit_history(): void
    {
        $manager = $this->makeStaff('kitchen-manager');
        Sanctum::actingAs($manager, ['kitchen:mobile']);
        $dish = $this->makeDish();
        $this->patchJson($this->availabilityUrl($dish), ['availability' => 'sold_out'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        foreach (['temporarily_unavailable', 'sold_out', 'available'] as $availability) {
            $this->patchJson($this->availabilityUrl($dish), [
                'availability' => $availability, 'reason' => 'Kitchen availability review',
            ])->assertOk()->assertJsonPath('data.item.availability', $availability);
        }
        $this->assertDatabaseCount('stock_transactions', 3);
        $this->assertDatabaseHas('stock_transactions', [
            'menu_item_id' => $dish->id, 'user_id' => $manager->id, 'transaction_type' => 'availability_change',
            'stock_before' => 20, 'stock_after' => 20, 'quantity_delta' => 0,
        ]);
        $this->assertSame(20, $dish->fresh()->prepared_stock_quantity);
    }

    private function makeKitchen(Branch $branch, string $name): Kitchen
    {
        return Kitchen::create([
            'company_id' => $branch->company_id, 'branch_id' => $branch->id,
            'name' => $name, 'code' => strtoupper($name), 'is_active' => true,
        ]);
    }

    private function makeStaff(string $role): User
    {
        $user = User::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => $role, 'email' => $role.'@stock.test', 'password' => 'stock-test-password', 'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());
        $user->kitchens()->attach($this->kitchen);
        return $user;
    }

    private function makeDish(array $attributes = [], ?Kitchen $kitchen = null): MenuItem
    {
        $sequence = ++$this->dishSequence;
        $dish = MenuItem::create(array_merge([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'category_id' => $this->category->id, 'menu_type_id' => $this->menuType->id,
            'name' => 'Prepared dish '.$sequence, 'code' => 'DISH-'.$sequence, 'selling_price' => 100,
            'stock_tracking_enabled' => true, 'prepared_stock_quantity' => 20,
            'availability' => 'available', 'is_active' => true,
        ], $attributes));
        KitchenMenuItem::create([
            'company_id' => $dish->company_id, 'branch_id' => $dish->branch_id,
            'menu_item_id' => $dish->id, 'kitchen_id' => ($kitchen ?? $this->kitchen)->id, 'is_active' => true,
        ]);
        return $dish;
    }

    private function recordMovement(MenuItem $dish, int $number, array $attributes = []): StockTransaction
    {
        $movement = StockTransaction::create(array_merge([
            'company_id' => $dish->company_id, 'branch_id' => $dish->branch_id,
            'menu_item_id' => $dish->id, 'user_id' => $this->staff->id,
            'transaction_type' => 'stock_in', 'quantity' => 1, 'quantity_delta' => 1,
            'stock_before' => $number - 1, 'stock_after' => $number, 'reason' => 'Movement '.$number,
        ], $attributes));
        $movement->forceFill(['created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00'])->save();
        return $movement;
    }

    private function historyUrl(MenuItem $dish): string
    {
        return '/api/v1/kitchen-mobile/stock/'.$dish->id.'/transactions';
    }

    private function adjustUrl(MenuItem $dish): string
    {
        return '/api/v1/kitchen-mobile/stock/'.$dish->id.'/adjustments';
    }

    private function availabilityUrl(MenuItem $dish): string
    {
        return '/api/v1/kitchen-mobile/stock/'.$dish->id.'/availability';
    }
}
