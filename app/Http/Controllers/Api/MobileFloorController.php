<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Floor;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileFloorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $this->assignedBranch($user);

        $floors = Floor::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $tablesByFloor = $this->tableQuery($user, $branch)
            ->whereIn('floor_id', $floors->modelKeys())
            ->get()
            ->groupBy('floor_id');

        return response()->json([
            'data' => [
                'branch' => $this->branchPayload($branch),
                'floors' => $floors->map(fn (Floor $floor) => [
                    'id' => $floor->id,
                    'name' => $floor->name,
                    'code' => $floor->code,
                    'tables' => ($tablesByFloor->get($floor->id) ?? collect())
                        ->map(fn (RestaurantTable $table) => $this->tablePayload($table))
                        ->values(),
                ])->values(),
            ],
        ]);
    }

    public function tables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'floor_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $branch = $this->assignedBranch($user);
        $query = $this->tableQuery($user, $branch);
        $floor = null;

        if (! empty($validated['floor_id'])) {
            $floor = Floor::query()
                ->where('company_id', $user->company_id)
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->findOrFail($validated['floor_id']);

            $query->where('floor_id', $floor->id);
        }

        return response()->json([
            'data' => [
                'branch' => $this->branchPayload($branch),
                'floor' => $floor ? [
                    'id' => $floor->id,
                    'name' => $floor->name,
                    'code' => $floor->code,
                ] : null,
                'tables' => $query->get()
                    ->map(fn (RestaurantTable $table) => $this->tablePayload($table))
                    ->values(),
            ],
        ]);
    }

    private function assignedBranch(User $user): Branch
    {
        abort_unless($user->company_id && $user->branch_id, 403, 'No branch is assigned to this account.');

        return Branch::query()
            ->where('company_id', $user->company_id)
            ->whereKey($user->branch_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function tableQuery(User $user, Branch $branch): Builder
    {
        $activeOrder = Order::query()
            ->whereColumn('orders.table_id', 'restaurant_tables.id')
            ->where('orders.company_id', $user->company_id)
            ->where('orders.branch_id', $branch->id)
            ->whereNotIn('orders.status', ['completed', 'cancelled'])
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->limit(1);

        return RestaurantTable::query()
            ->where('restaurant_tables.company_id', $user->company_id)
            ->where('restaurant_tables.branch_id', $branch->id)
            ->where('restaurant_tables.is_active', true)
            ->whereHas('floor', fn (Builder $floor) => $floor
                ->where('company_id', $user->company_id)
                ->where('branch_id', $branch->id)
                ->where('is_active', true))
            ->select([
                'restaurant_tables.id',
                'restaurant_tables.floor_id',
                'restaurant_tables.name',
                'restaurant_tables.seating_capacity',
                'restaurant_tables.table_status',
            ])
            ->addSelect([
                'active_order_id' => (clone $activeOrder)->select('orders.id'),
                'active_order_number' => (clone $activeOrder)->select('orders.order_number'),
            ])
            ->orderBy('restaurant_tables.name');
    }

    private function branchPayload(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
        ];
    }

    private function tablePayload(RestaurantTable $table): array
    {
        return [
            'id' => $table->id,
            'floor_id' => $table->floor_id,
            'name' => $table->name,
            'seating_capacity' => $table->seating_capacity,
            'status' => strtolower($table->table_status),
            'active_order_id' => $table->active_order_id ? (int) $table->active_order_id : null,
            'active_order_number' => $table->active_order_number,
        ];
    }
}
