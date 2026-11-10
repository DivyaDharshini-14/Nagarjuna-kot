<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->company_id && $user->branch_id, 403, 'No branch is assigned to this account.');

        $branch = Branch::query()
            ->where('company_id', $user->company_id)
            ->whereKey($user->branch_id)
            ->where('is_active', true)
            ->firstOrFail();

        $categories = Category::query()
            ->where('company_id', $user->company_id)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'display_order']);

        $itemsByCategory = MenuItem::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query
                ->where('company_id', $user->company_id)
                ->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branch->id))
                ->where('is_active', true))
            ->whereHas('menuType', fn ($query) => $query
                ->where('company_id', $user->company_id)
                ->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branch->id))
                ->where('is_active', true))
            ->with([
                'category:id,name',
                'menuType:id,name',
            ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category_id');

        return response()->json([
            'data' => [
                'branch' => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ],
                'categories' => $categories->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'items' => ($itemsByCategory->get($category->id) ?? collect())
                        ->map(fn (MenuItem $item) => $this->itemPayload($item))
                        ->values(),
                ])->values(),
            ],
        ]);
    }

    private function itemPayload(MenuItem $item): array
    {
        $availability = strtolower($item->availability);
        $trackingEnabled = (bool) $item->stock_tracking_enabled;
        $stockQuantity = $item->prepared_stock_quantity;
        $stockConfigured = ! $trackingEnabled || $stockQuantity !== null;
        $inStock = ! $trackingEnabled || ($stockQuantity !== null && $stockQuantity > 0);

        return [
            'id' => $item->id,
            'category_id' => $item->category_id,
            'name' => $item->name,
            'code' => $item->code,
            'description' => $item->description,
            'image_path' => $item->image ? '/storage/'.ltrim($item->image, '/') : null,
            'selling_price' => $item->selling_price,
            'menu_type' => [
                'id' => $item->menuType->id,
                'name' => $item->menuType->name,
            ],
            'availability' => $availability,
            'stock_tracking_enabled' => $trackingEnabled,
            'prepared_stock_quantity' => $trackingEnabled && $stockQuantity !== null
                ? (int) $stockQuantity
                : null,
            'stock_configured' => $stockConfigured,
            'is_orderable' => $availability === 'available' && $inStock && $stockConfigured,
        ];
    }
}
