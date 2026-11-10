<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Branch $branch;
    protected Floor $floor;
    protected RestaurantTable $table;
    protected Kitchen $biryaniKitchen;
    protected Kitchen $chineseKitchen;
    protected Category $category;
    protected MenuType $menuType;
    protected MenuItem $chickenBiryani;
    protected MenuItem $friedRice;
    protected User $waiter;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->company = Company::create([
            'name' => 'Nagarjuna Restaurant Group',
            'code' => 'NAG',
            'is_active' => true,
        ]);

        $this->branch = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Indiranagar Branch',
            'code' => 'IND01',
            'is_active' => true,
        ]);

        $this->floor = Floor::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ground Floor',
            'code' => 'GF',
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'name' => 'Table 101',
            'seating_capacity' => 4,
            'table_status' => 'Available',
            'is_active' => true,
        ]);

        $this->biryaniKitchen = Kitchen::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Biryani Kitchen',
            'code' => 'K-BIR',
            'is_active' => true,
        ]);

        $this->chineseKitchen = Kitchen::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Chinese Kitchen',
            'code' => 'K-CHN',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Main Course',
            'code' => 'MAIN',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->menuType = MenuType::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Non-Veg',
            'is_active' => true,
        ]);

        $this->chickenBiryani = MenuItem::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'menu_type_id' => $this->menuType->id,
            'name' => 'Chicken Biryani',
            'code' => 'CB01',
            'selling_price' => 320.00,
            'display_order' => 1,
            'stock_tracking_enabled' => false,
            'availability' => 'available',
            'is_active' => true,
        ]);

        $this->friedRice = MenuItem::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'menu_type_id' => $this->menuType->id,
            'name' => 'Veg Fried Rice',
            'code' => 'VFR01',
            'selling_price' => 220.00,
            'display_order' => 2,
            'stock_tracking_enabled' => false,
            'availability' => 'available',
            'is_active' => true,
        ]);

        // Map Chicken Biryani -> Biryani Kitchen
        KitchenMenuItem::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->chickenBiryani->id,
            'kitchen_id' => $this->biryaniKitchen->id,
            'is_active' => true,
        ]);

        // Map Veg Fried Rice -> Chinese Kitchen
        KitchenMenuItem::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->friedRice->id,
            'kitchen_id' => $this->chineseKitchen->id,
            'is_active' => true,
        ]);

        $waiterRole = Role::where('slug', 'waiter')->firstOrFail();
        $this->waiter = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ramesh Waiter',
            'email' => 'waiter@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->waiter->roles()->attach($waiterRole);

        $managerRole = Role::where('slug', 'manager')->firstOrFail();
        $this->manager = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Branch Manager',
            'email' => 'manager@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->manager->roles()->attach($managerRole);
    }

    public function test_waiter_can_submit_order_and_split_kots_kitchen_wise(): void
    {
        $response = $this->actingAs($this->waiter)->post(route('orders.store'), [
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'guest_count' => 3,
            'customer_name' => 'Sunil Kumar',
            'items' => [
                [
                    'menu_item_id' => $this->chickenBiryani->id,
                    'quantity' => 2,
                    'special_instructions' => 'Extra spicy',
                ],
                [
                    'menu_item_id' => $this->friedRice->id,
                    'quantity' => 1,
                    'special_instructions' => 'No spring onions',
                ],
            ],
        ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));

        // 1. Order details
        $this->assertStringStartsWith('ORD-', $order->order_number);
        $this->assertEquals(2 * 320.00 + 1 * 220.00, (float) $order->total_amount);
        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals($this->waiter->id, $order->waiter_id);

        // 2. Table status updated to occupied
        $this->assertEquals('Occupied', $this->table->fresh()->table_status);

        // 3. Order items
        $this->assertCount(2, $order->items);

        // 4. Kitchen-wise KOT generation: 2 separate KOTs for Biryani and Chinese Kitchen
        $kots = $order->kotTickets;
        $this->assertCount(2, $kots);

        $biryaniKot = $kots->firstWhere('kitchen_id', $this->biryaniKitchen->id);
        $this->assertNotNull($biryaniKot);
        $this->assertStringStartsWith('KOT-', $biryaniKot->kot_number);
        $this->assertCount(1, $biryaniKot->items);
        $this->assertEquals($this->chickenBiryani->id, $biryaniKot->items->first()->menu_item_id);
        $this->assertEquals(2, $biryaniKot->items->first()->quantity);

        $chineseKot = $kots->firstWhere('kitchen_id', $this->chineseKitchen->id);
        $this->assertNotNull($chineseKot);
        $this->assertCount(1, $chineseKot->items);
        $this->assertEquals($this->friedRice->id, $chineseKot->items->first()->menu_item_id);
        $this->assertEquals(1, $chineseKot->items->first()->quantity);
    }

    public function test_additional_items_generate_only_new_kots(): void
    {
        // 1. Initial order with 1 Chicken Biryani
        $this->actingAs($this->waiter)->post(route('orders.store'), [
            'table_id' => $this->table->id,
            'items' => [
                ['menu_item_id' => $this->chickenBiryani->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::firstOrFail();
        $this->assertCount(1, $order->kotTickets);

        // 2. Customer adds Fried Rice later
        $this->actingAs($this->waiter)->post(route('orders.add-items', $order), [
            'items' => [
                ['menu_item_id' => $this->friedRice->id, 'quantity' => 2],
            ],
        ]);

        $order->refresh();
        $this->assertEquals(1 * 320.00 + 2 * 220.00, (float) $order->total_amount);
        $this->assertCount(2, $order->items);

        // Should now have 2 KOT tickets: first for Biryani, second for the newly added Fried Rice
        $this->assertCount(2, $order->kotTickets);
        $newKot = $order->kotTickets->sortByDesc('id')->first();
        $this->assertEquals($this->chineseKitchen->id, $newKot->kitchen_id);
        $this->assertEquals(2, $newKot->items->first()->quantity);
    }

    public function test_kot_status_update_and_order_completion(): void
    {
        $this->actingAs($this->waiter)->post(route('orders.store'), [
            'table_id' => $this->table->id,
            'items' => [
                ['menu_item_id' => $this->chickenBiryani->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::firstOrFail();
        $ticket = $order->kotTickets->firstOrFail();

        // Advance KOT to preparing
        $this->actingAs($this->manager)->patch(route('kots.update-status', $ticket), [
            'status' => 'preparing',
        ]);
        $this->assertEquals('preparing', $ticket->fresh()->ticket_status);
        $this->assertEquals('preparing', $order->fresh()->status);

        // Complete order and free table
        $this->actingAs($this->manager)->patch(route('orders.update-status', $order), [
            'status' => 'completed',
        ]);
        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals('Available', $this->table->fresh()->table_status);
    }

    public function test_order_api_store_and_kitchen_kot(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/api/v1/orders', [
            'table_id' => $this->table->id,
            'items' => [
                ['menu_item_id' => $this->chickenBiryani->id, 'quantity' => 1],
                ['menu_item_id' => $this->friedRice->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'order' => [
                'id',
                'order_number',
                'total_amount',
                'kot_tickets',
            ],
        ]);

        // Kitchen Display API endpoint retrieves only tickets for that specific kitchen
        $biryaniApiResponse = $this->actingAs($this->waiter)->getJson("/api/v1/kitchens/{$this->biryaniKitchen->id}/kot");
        $biryaniApiResponse->assertOk();
        $biryaniApiResponse->assertJsonFragment(['name' => 'Chicken Biryani']);
        $biryaniApiResponse->assertJsonMissing(['name' => 'Veg Fried Rice']);
    }

    public function test_admin_has_full_orders_and_kot_management_access(): void
    {
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@nagarjuna.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $admin->roles()->attach($adminRole);

        // Admin can view orders list
        $this->actingAs($admin)->get(route('orders.index'))->assertOk();

        // Admin can view order creation form
        $this->actingAs($admin)->get(route('orders.create'))->assertOk();

        // Admin can view KOT list
        $this->actingAs($admin)->get(route('kots.index'))->assertOk();

        // Admin can view Kitchen Display
        $this->actingAs($admin)->get(route('kots.display', $this->biryaniKitchen))->assertOk();

        // Admin can create an order directly
        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'table_id' => $this->table->id,
            'items' => [
                ['menu_item_id' => $this->chickenBiryani->id, 'quantity' => 1],
            ],
        ]);
        $order = Order::latest()->first();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertNotNull($order);
    }

    public function test_manager_can_monitor_branch_orders_and_kots_with_branch_isolation(): void
    {
        // Manager can view orders and KOTs for their branch
        $this->actingAs($this->manager)->get(route('orders.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('kots.index'))->assertOk();

        // Another branch setup
        $branchB = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Branch B',
            'code' => 'BR-B',
            'is_active' => true,
        ]);
        $floorB = Floor::create([
            'company_id' => $this->company->id,
            'branch_id' => $branchB->id,
            'name' => 'Floor B',
            'code' => 'FL-B',
            'is_active' => true,
        ]);
        $tableB = RestaurantTable::create([
            'company_id' => $this->company->id,
            'branch_id' => $branchB->id,
            'floor_id' => $floorB->id,
            'name' => 'Table B-1',
            'seating_capacity' => 4,
            'table_status' => 'Available',
            'is_active' => true,
        ]);
        $orderB = Order::create([
            'order_number' => 'ORD-BRANCH-B-001',
            'company_id' => $this->company->id,
            'branch_id' => $branchB->id,
            'table_id' => $tableB->id,
            'waiter_id' => $this->manager->id,
            'status' => 'confirmed',
            'total_amount' => 100.00,
        ]);

        // Manager of Branch A cannot access order in Branch B (403 Forbidden)
        $this->actingAs($this->manager)->get(route('orders.show', $orderB))->assertForbidden();
    }

    public function test_waiter_cannot_access_kot_monitoring(): void
    {
        // Waiter can access orders
        $this->actingAs($this->waiter)->get(route('orders.index'))->assertOk();
        $this->actingAs($this->waiter)->get(route('orders.create'))->assertOk();

        // Waiter CANNOT access KOT monitoring list or Kitchen Display (403 Forbidden)
        $this->actingAs($this->waiter)->get(route('kots.index'))->assertForbidden();
        $this->actingAs($this->waiter)->get(route('kots.display', $this->biryaniKitchen))->assertForbidden();
    }

    public function test_multiple_additional_items_grouped_correctly_by_kitchen(): void
    {
        // Create 3rd kitchen (Starters Kitchen) and 3rd dish (Gobi Manchurian)
        $startersKitchen = Kitchen::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Starters Kitchen',
            'code' => 'K-STR',
            'is_active' => true,
        ]);
        $gobiManchurian = MenuItem::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'menu_type_id' => $this->menuType->id,
            'name' => 'Gobi Manchurian',
            'code' => 'GM01',
            'selling_price' => 180.00,
            'is_active' => true,
        ]);
        KitchenMenuItem::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'menu_item_id' => $gobiManchurian->id,
            'kitchen_id' => $startersKitchen->id,
            'is_active' => true,
        ]);

        // 1. Initial Order with Chicken Biryani (Biryani Kitchen)
        $this->actingAs($this->waiter)->post(route('orders.store'), [
            'table_id' => $this->table->id,
            'items' => [
                ['menu_item_id' => $this->chickenBiryani->id, 'quantity' => 1],
            ],
        ]);
        $order = Order::latest()->firstOrFail();
        $this->assertCount(1, $order->kotTickets);

        // 2. Later, customer orders 2 additional dishes: Fried Rice (Chinese Kitchen) + Gobi Manchurian (Starters Kitchen)
        $this->actingAs($this->waiter)->post(route('orders.add-items', $order), [
            'items' => [
                ['menu_item_id' => $this->friedRice->id, 'quantity' => 2],
                ['menu_item_id' => $gobiManchurian->id, 'quantity' => 1],
            ],
        ]);

        $order->refresh();
        // 1 Chicken Biryani + 2 Fried Rice + 1 Gobi Manchurian
        $this->assertEquals(1 * 320.00 + 2 * 220.00 + 1 * 180.00, (float) $order->total_amount);

        // All 3 items must be in order_items table under the SAME order_id
        $this->assertCount(3, $order->items);
        $this->assertEquals([$order->id, $order->id, $order->id], $order->items->pluck('order_id')->all());

        // Total 3 KOT tickets:
        // Ticket 1: Biryani Kitchen (from initial order)
        // Ticket 2: Chinese Kitchen (newly added)
        // Ticket 3: Starters Kitchen (newly added)
        $this->assertCount(3, $order->kotTickets);

        $newTickets = $order->kotTickets->where('id', '>', $order->kotTickets->first()->id);
        $this->assertCount(2, $newTickets);
        $this->assertTrue($newTickets->pluck('kitchen_id')->contains($this->chineseKitchen->id));
        $this->assertTrue($newTickets->pluck('kitchen_id')->contains($startersKitchen->id));
    }
}

