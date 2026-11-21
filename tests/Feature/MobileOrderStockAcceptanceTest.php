<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Floor;
use App\Models\ItemCancellation;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\StockTransaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileOrderStockAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private Floor $floor;
    private RestaurantTable $table;
    private Kitchen $kitchen;
    private MenuItem $dish;
    private User $waiter;
    private User $cook;
    private User $manager;
    private string $waiterToken;
    private string $cookToken;
    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // This suite must never reserve or cancel real restaurant stock.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);

        $this->company = Company::create(['name' => 'Stock Acceptance', 'code' => 'STOCK', 'is_active' => true]);
        $this->branch = Branch::create([
            'company_id' => $this->company->id, 'name' => 'Test branch', 'code' => 'TEST',
            'is_active' => true, 'cancellation_approval_required' => false,
        ]);
        $this->floor = Floor::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => 'Ground', 'code' => 'G', 'is_active' => true,
        ]);
        $this->table = $this->newTable('T1');
        $this->kitchen = Kitchen::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => 'Kitchen', 'code' => 'K', 'is_active' => true,
        ]);
        $category = Category::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $type = MenuType::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => 'Veg', 'is_active' => true,
        ]);
        $this->dish = MenuItem::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'category_id' => $category->id, 'menu_type_id' => $type->id,
            'name' => 'Prepared rice', 'code' => 'RICE', 'selling_price' => 100,
            'stock_tracking_enabled' => true, 'prepared_stock_quantity' => 10,
            'availability' => 'available', 'is_active' => true,
        ]);
        $this->mapDish($this->dish);
        $this->waiter = $this->staff('waiter');
        $this->cook = $this->staff('kitchen-staff');
        $this->cook->kitchens()->attach($this->kitchen);
        $this->manager = $this->staff('manager');
        $this->waiterToken = $this->waiter->createToken('Acceptance waiter', ['waiter:mobile'])->plainTextToken;
        $this->cookToken = $this->cook->createToken('Acceptance kitchen', ['kitchen:mobile'])->plainTextToken;
        $this->managerToken = $this->manager->createToken('Acceptance manager', ['waiter:mobile'])->plainTextToken;
        $this->useToken($this->waiterToken);
    }

    public function test_mobile_submission_retry_deducts_once_and_rejects_a_changed_payload(): void
    {
        $key = (string) Str::uuid();
        $payload = $this->orderPayload(3);
        $created = $this->postJson('/api/v1/mobile/orders', $payload, ['Idempotency-Key' => $key])
            ->assertCreated()->assertJsonPath('data.replayed', false);
        $orderId = $created->json('data.order.id');

        $this->postJson('/api/v1/mobile/orders', $payload, ['Idempotency-Key' => $key])
            ->assertOk()->assertJsonPath('data.replayed', true)->assertJsonPath('data.order.id', $orderId);
        $this->postJson('/api/v1/mobile/orders', $this->orderPayload(4), ['Idempotency-Key' => $key])
            ->assertConflict();

        $this->assertSame(7, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('kot_tickets', 1);
        $this->assertDatabaseCount('stock_transactions', 1);
        $this->assertDatabaseHas('stock_transactions', [
            'menu_item_id' => $this->dish->id, 'order_id' => $orderId, 'user_id' => $this->waiter->id,
            'transaction_type' => 'order_deduction', 'quantity' => 3, 'quantity_delta' => -3,
            'stock_before' => 10, 'stock_after' => 7,
        ]);
    }

    public function test_insufficient_stock_rolls_back_all_dishes_tickets_and_idempotency_reservations(): void
    {
        $limitedDish = $this->dish->replicate();
        $limitedDish->fill(['name' => 'Limited rice', 'code' => 'LIMITED', 'prepared_stock_quantity' => 1])->save();
        $this->mapDish($limitedDish);
        $payload = $this->orderPayload(2);
        $payload['items'][] = ['menu_item_id' => $limitedDish->id, 'quantity' => 2];

        $this->postJson('/api/v1/mobile/orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(10, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertSame(1, $limitedDish->fresh()->prepared_stock_quantity);
        $this->assertSame('Available', $this->table->fresh()->table_status);
        foreach (['orders', 'order_items', 'kot_tickets', 'kot_items', 'stock_transactions', 'mobile_api_idempotency_keys'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_additional_mobile_items_and_retries_create_only_one_extra_stock_movement(): void
    {
        $orderId = $this->createOrder(2);
        $key = (string) Str::uuid();
        $payload = ['table_id' => $this->table->id, 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 3]]];
        $url = "/api/v1/mobile/orders/{$orderId}/items";

        $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.replayed', false);
        $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.replayed', true);

        $this->assertSame(5, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('kot_tickets', 2);
        $this->assertDatabaseCount('stock_transactions', 2);
        $this->assertSame(5, (int) StockTransaction::where('order_id', $orderId)->sum('quantity'));
    }

    public function test_cancelling_unprepared_portions_restores_only_the_cancelled_quantity_and_cannot_over_restore(): void
    {
        $orderId = $this->createOrder(3);
        $item = Order::findOrFail($orderId)->items()->firstOrFail();
        $url = "/api/v1/mobile/orders/{$orderId}/cancellations";
        $this->postJson($url, [
            'reason' => 'Guest reduced portions', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertOk();
        $this->assertSame(8, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertSame(1, $item->fresh()->cancelled_quantity);

        $this->postJson($url, ['reason' => 'Guests left', 'cancel_all' => true])->assertOk();
        $this->postJson($url, ['reason' => 'Guests left', 'cancel_all' => true])->assertUnprocessable();

        $this->assertSame(10, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertSame(3, $item->fresh()->cancelled_quantity);
        $this->assertSame(3, (int) StockTransaction::where('transaction_type', 'order_cancellation_reversal')->sum('quantity'));
        $this->assertSame(0, StockTransaction::where('transaction_type', 'wastage')->count());
        $this->assertSame(3, (int) ItemCancellation::sum('stock_reversed_quantity'));
    }

    public function test_prepared_cancellation_records_wastage_without_returning_portions_to_available_stock(): void
    {
        $orderId = $this->createOrder(3);
        $ticket = Order::findOrFail($orderId)->kotTickets()->firstOrFail();
        $this->useToken($this->cookToken);
        foreach (['accepted', 'preparing', 'ready'] as $status) {
            $this->patchJson("/api/v1/kitchen-mobile/kot-tickets/{$ticket->id}/status", ['status' => $status])->assertOk();
        }

        $this->useToken($this->waiterToken);
        $this->postJson("/api/v1/mobile/orders/{$orderId}/cancellations", [
            'reason' => 'Guests left after preparation', 'cancel_all' => true,
        ])->assertOk();

        $this->assertSame(7, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertDatabaseHas('stock_transactions', [
            'order_id' => $orderId, 'menu_item_id' => $this->dish->id, 'transaction_type' => 'wastage',
            'quantity' => 3, 'quantity_delta' => 0, 'stock_before' => 7, 'stock_after' => 7,
        ]);
        $this->assertSame(0, StockTransaction::where('transaction_type', 'order_cancellation_reversal')->count());
        $this->assertDatabaseHas('item_cancellations', ['order_id' => $orderId, 'stock_reversed_quantity' => 0, 'wastage_quantity' => 3]);
        $this->assertSame('cancelled', $ticket->fresh()->ticket_status);
    }

    public function test_ready_dishes_leave_both_kitchen_boards_and_update_order_readiness(): void
    {
        $this->dish->update(['short_code' => 'PR']);
        $secondDish = $this->dish->replicate();
        $secondDish->fill(['name' => 'Mutton Fry', 'code' => 'MUTTON-FRY', 'short_code' => 'MF'])->save();
        $this->mapDish($secondDish);

        $payload = $this->orderPayload(1);
        $payload['items'][] = ['menu_item_id' => $secondDish->id, 'quantity' => 1];
        $orderId = $this->postJson('/api/v1/mobile/orders', $payload, [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order.id');
        $order = Order::findOrFail($orderId);
        $ticket = $order->kotTickets()->firstOrFail();
        [$first, $last] = $ticket->items()->orderBy('id')->get()->all();
        $mobileFeed = '/api/v1/kitchen-mobile/kot';
        $webFeed = route('kds.feed', $this->kitchen);
        $displayToken = Str::random(64);
        $this->kitchen->update(['display_token_hash' => hash('sha256', $displayToken)]);
        $display = route('kds.registered-display', $displayToken);

        $this->actingAs($this->manager, 'web');
        $this->get(route('kds.branch.kitchen', [$this->branch->code, $this->kitchen->code]))
            ->assertOk()->assertSee('Active Orders')->assertDontSee('data-ticket=', false)->assertDontSee('data-item=', false);
        $this->get($display)->assertOk()->assertDontSee('<form', false);
        $this->postJson("/kds/tickets/{$ticket->id}/status", ['status' => 'accepted'])->assertNotFound();
        $this->postJson("/kds/items/{$first->id}/status", ['status' => 'ready'])->assertNotFound();
        $this->patchJson("{$display}/tickets/{$ticket->id}/status", ['status' => 'accepted'])->assertNotFound();
        $this->patchJson("{$display}/items/{$first->id}/ready")->assertNotFound();
        $this->getJson($webFeed)->assertJsonCount(2, 'active_tickets.0.items')
            ->assertJsonPath('active_tickets.0.ticket_status', 'new');

        $this->useToken($this->cookToken);
        $this->patchJson(route('api.kitchen-mobile.kot-tickets.status', $ticket), ['status' => 'accepted'])->assertOk();
        $this->getJson($mobileFeed)->assertOk()
            ->assertJsonPath('data.tickets.0.items.0.menu_item.short_code', 'PR');
        $itemReady = route('api.kitchen-mobile.kot-items.ready', $first);
        $this->patchJson($itemReady)->assertOk();
        $this->patchJson($itemReady)->assertOk();
        $this->getJson($mobileFeed)->assertOk()
            ->assertJsonCount(1, 'data.tickets.0.items')
            ->assertJsonPath('data.tickets.0.items.0.id', $last->id)
            ->assertJsonPath('data.tickets.0.order.status', 'partially_ready');

        $this->actingAs($this->manager, 'web');
        $this->getJson($webFeed)->assertOk()
            ->assertJsonCount(1, 'active_tickets.0.items')
            ->assertJsonPath('active_tickets.0.items.0.short_code', 'MF')
            ->assertJsonPath('active_tickets.0.order_status', 'partially_ready');

        $this->get($display)->assertOk()->assertSee('Partially Ready')->assertSee('>MF</div>', false)
            ->assertDontSee('>PR</div>', false)->assertDontSee('<form', false);

        $this->useToken($this->cookToken);
        $this->patchJson(route('api.kitchen-mobile.kot-items.ready', $last))->assertOk();
        $this->getJson($mobileFeed)->assertOk()->assertJsonCount(0, 'data.tickets');
        $this->assertSame('ready', $ticket->fresh()->ticket_status);
        $this->assertSame('ready', $order->fresh()->status);

        $this->actingAs($this->manager, 'web');
        $this->get($display)->assertOk()->assertSee('No pending orders')->assertDontSee('>MF</div>', false);
        $this->getJson($webFeed)->assertOk()->assertJsonCount(0, 'active_tickets');

        $this->useToken($this->waiterToken);
        $this->getJson("/api/v1/mobile/orders/{$orderId}")->assertOk()->assertJsonPath('data.order.status', 'ready');
        $this->postJson("/api/v1/mobile/orders/{$orderId}/items", $this->orderPayload(1), [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('data.order.status', 'partially_ready');
    }

    public function test_item_master_saves_and_validates_the_short_code(): void
    {
        $this->actingAs($this->manager, 'web');
        $this->get(route('menu-items.create'))->assertOk()->assertSee('Short Code');
        $item = [
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->dish->category_id,
            'menu_type_id' => $this->dish->menu_type_id,
            'name' => 'Chicken Biryani',
            'code' => 'BIRYANI-CHICKEN',
            'short_code' => 'cb',
            'selling_price' => 150,
            'display_order' => 0,
            'stock_tracking_enabled' => false,
            'is_active' => true,
        ];

        $this->post(route('menu-items.store'), $item)->assertRedirect(route('menu-items.index'));
        $chicken = MenuItem::where('code', $item['code'])->firstOrFail();
        $this->assertSame('CB', $chicken->short_code);

        $this->post(route('menu-items.store'), array_replace($item, ['code' => 'OTHER-BIRYANI', 'short_code' => 'Cb']))
            ->assertSessionHasErrors('short_code');

        $this->put(route('menu-items.update', $chicken), $item)->assertSessionHasNoErrors();
        $this->put(route('menu-items.update', $chicken), array_replace($item, ['short_code' => 'chb']))
            ->assertRedirect(route('menu-items.index'));
        $this->assertSame('CHB', $chicken->fresh()->short_code);
    }

    public function test_cancelling_the_last_unfinished_dish_makes_the_order_ready(): void
    {
        $payload = $this->orderPayload(1);
        $payload['items'][] = ['menu_item_id' => $this->dish->id, 'quantity' => 2];
        $orderId = $this->postJson('/api/v1/mobile/orders', $payload, [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order.id');
        $order = Order::findOrFail($orderId);
        $ticket = $order->kotTickets()->firstOrFail();
        [$first, $last] = $ticket->items()->orderBy('id')->get()->all();

        $this->useToken($this->cookToken);
        $this->patchJson(route('api.kitchen-mobile.kot-tickets.status', $ticket), ['status' => 'accepted'])->assertOk();
        $this->patchJson(route('api.kitchen-mobile.kot-items.ready', $first))->assertOk();

        $this->useToken($this->waiterToken);
        $this->postJson("/api/v1/mobile/orders/{$orderId}/cancellations", [
            'reason' => 'Guest changed their mind',
            'items' => [['order_item_id' => $last->order_item_id, 'quantity' => 2]],
        ])->assertOk();

        $this->assertSame('ready', $ticket->fresh()->ticket_status);
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_pending_cancellation_does_not_restock_until_separate_manager_approval_and_cannot_be_approved_twice(): void
    {
        $this->branch->update(['cancellation_approval_required' => true]);
        $orderId = $this->createOrder(2);
        $request = $this->postJson("/api/v1/mobile/orders/{$orderId}/cancellations", [
            'reason' => 'Guest requested cancellation', 'cancel_all' => true,
        ])->assertAccepted();
        $batch = $request->json('data.request_batch');
        $this->assertSame(8, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertDatabaseCount('stock_transactions', 1);

        $this->useToken($this->managerToken);
        $this->postJson("/api/v1/mobile/cancellation-requests/{$batch}/approve")->assertOk();
        $this->postJson("/api/v1/mobile/cancellation-requests/{$batch}/approve")->assertNotFound();
        $this->assertSame(10, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertSame(1, StockTransaction::where('transaction_type', 'order_cancellation_reversal')->count());
    }

    public function test_a_later_order_cannot_oversell_remaining_stock(): void
    {
        // Sequential requests verify revalidation; real concurrent locking needs MySQL testing.
        $this->createOrder(8);
        $otherTable = $this->newTable('T2');
        $payload = $this->orderPayload(3);
        $payload['table_id'] = $otherTable->id;
        $this->postJson('/api/v1/mobile/orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(2, $this->dish->fresh()->prepared_stock_quantity);
        $this->assertSame('Available', $otherTable->fresh()->table_status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_transactions', 1);
    }

    public function test_sold_out_unavailable_and_zero_stock_dishes_are_not_orderable_in_the_mobile_menu_or_submission(): void
    {
        foreach ([['sold_out', 10], ['temporarily_unavailable', 10], ['available', 0]] as [$availability, $quantity]) {
            $this->dish->update(['availability' => $availability, 'prepared_stock_quantity' => $quantity]);
            $this->getJson('/api/v1/menu')->assertOk()
                ->assertJsonPath('data.categories.0.items.0.id', $this->dish->id)
                ->assertJsonPath('data.categories.0.items.0.availability', $availability)
                ->assertJsonPath('data.categories.0.items.0.prepared_stock_quantity', $quantity)
                ->assertJsonPath('data.categories.0.items.0.is_orderable', false);
            $this->postJson('/api/v1/mobile/orders', $this->orderPayload(1), [
                'Idempotency-Key' => (string) Str::uuid(),
            ])->assertUnprocessable()->assertJsonValidationErrors('items');
            $this->assertSame($quantity, $this->dish->fresh()->prepared_stock_quantity);
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('kot_tickets', 0);
        $this->assertDatabaseCount('stock_transactions', 0);
    }

    private function useToken(string $token): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
    }

    private function createOrder(int $quantity): int
    {
        return $this->postJson('/api/v1/mobile/orders', $this->orderPayload($quantity), [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order.id');
    }

    private function orderPayload(int $quantity): array
    {
        return [
            'table_id' => $this->table->id, 'guest_count' => 1,
            'items' => [['menu_item_id' => $this->dish->id, 'quantity' => $quantity]],
        ];
    }

    private function newTable(string $name): RestaurantTable
    {
        return RestaurantTable::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'floor_id' => $this->floor->id,
            'name' => $name, 'seating_capacity' => 4, 'table_status' => 'Available', 'is_active' => true,
        ]);
    }

    private function mapDish(MenuItem $dish): void
    {
        KitchenMenuItem::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'menu_item_id' => $dish->id, 'kitchen_id' => $this->kitchen->id, 'is_active' => true,
        ]);
    }

    private function staff(string $role): User
    {
        $user = User::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'name' => $role, 'email' => $role.'@acceptance.test', 'password' => 'test-password', 'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }
}
