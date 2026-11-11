<?php

namespace App\Http\Controllers;

use App\Events\MenuAvailabilityChanged;
use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\StockTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $menuItems = $this->applyBranchScope(MenuItem::query()->with(['company', 'branch', 'category', 'menuType']));
        $this->applySearch($menuItems, $request, ['name', 'code']);
        $this->applyStatus($menuItems, $request);

        $menuItems = $menuItems->orderBy('display_order')->orderBy('name')->paginate(10)->withQueryString();

        return view('menu-items.index', compact('menuItems'));
    }

    public function create(): View
    {
        return view('menu-items.create', $this->selectionState());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateMenuItem($request);
        $this->assertTenant($validated);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        }

        if (! empty($validated['stock_tracking_enabled'])) {
            $request->validate(['stock_adjustment_reason' => ['required', 'string', 'min:3', 'max:500']]);
        }
        $createdItem = DB::transaction(function () use ($validated, $request): MenuItem {
            $menuItem = MenuItem::create($validated);
            if ($menuItem->stock_tracking_enabled && $menuItem->prepared_stock_quantity !== null) {
                StockTransaction::create([
                    'company_id' => $menuItem->company_id,
                    'branch_id' => $menuItem->branch_id,
                    'menu_item_id' => $menuItem->id,
                    'user_id' => $request->user()->id,
                    'transaction_type' => 'opening_stock',
                    'quantity' => $menuItem->prepared_stock_quantity,
                    'quantity_delta' => $menuItem->prepared_stock_quantity,
                    'stock_before' => 0,
                    'stock_after' => $menuItem->prepared_stock_quantity,
                    'reason' => trim($request->input('stock_adjustment_reason')),
                ]);
            }
            return $menuItem;
        });
        event(new MenuAvailabilityChanged(
            (int) $createdItem->branch_id,
            (int) $createdItem->id,
            (string) $createdItem->availability,
            $createdItem->prepared_stock_quantity === null ? null : (int) $createdItem->prepared_stock_quantity,
        ));

        return redirect()->route('menu-items.index')->with('success', 'Menu item created successfully.');
    }

    public function edit(MenuItem $menuItem): View
    {
        $this->authorizeBranch($menuItem->branch);

        return view('menu-items.edit', array_merge($this->selectionState($menuItem), compact('menuItem')));
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizeBranch($menuItem->branch);
        $this->mergeLockedTenant($request);

        $validated = $this->validateMenuItem($request, $menuItem);
        $this->assertTenant($validated);

        if ($menuItem->kitchenMenuItems()->exists() && (
            (int) $menuItem->company_id !== (int) $validated['company_id']
            || (int) $menuItem->branch_id !== (int) $validated['branch_id']
        )) {
            return back()->withErrors([
                'branch_id' => 'This menu item has kitchen mappings and its branch cannot be changed.',
            ])->withInput();
        }

        if ($request->hasFile('image')) {
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }

            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        } else {
            unset($validated['image']);
        }

        $previousStock = $menuItem->prepared_stock_quantity;
        $previousTrackingEnabled = (bool) $menuItem->stock_tracking_enabled;
        $trackingEnabled = ! empty($validated['stock_tracking_enabled']);
        $previousAvailability = $menuItem->availability;
        $availabilityChanged = $previousAvailability !== $validated['availability'];
        $stockChanged =
            $trackingEnabled
            && (! $previousTrackingEnabled || $previousStock === null || (int) $previousStock !== (int) ($validated['prepared_stock_quantity'] ?? 0));
        if ($stockChanged || $availabilityChanged) {
            $request->validate(['stock_adjustment_reason' => ['required', 'string', 'min:3', 'max:500']]);
        }
        DB::transaction(function () use ($menuItem, $validated, $request, $previousStock, $previousTrackingEnabled, $stockChanged, $availabilityChanged, $previousAvailability): void {
            $menuItem->update($validated);
            if ($stockChanged && $menuItem->prepared_stock_quantity !== null) {
                $isOpeningStock = ! $previousTrackingEnabled || $previousStock === null;
                $before = $isOpeningStock ? 0 : (int) $previousStock;
                $after = (int) $menuItem->prepared_stock_quantity;
                StockTransaction::create([
                    'company_id' => $menuItem->company_id,
                    'branch_id' => $menuItem->branch_id,
                    'menu_item_id' => $menuItem->id,
                    'user_id' => $request->user()->id,
                    'transaction_type' => $isOpeningStock ? 'opening_stock' : 'manual_adjustment',
                    'quantity' => abs($after - $before),
                    'quantity_delta' => $after - $before,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'reason' => trim($request->input('stock_adjustment_reason')),
                ]);
            }
            if ($availabilityChanged) {
                $stock = (int) ($menuItem->prepared_stock_quantity ?? 0);
                StockTransaction::create([
                    'company_id' => $menuItem->company_id,
                    'branch_id' => $menuItem->branch_id,
                    'menu_item_id' => $menuItem->id,
                    'user_id' => $request->user()->id,
                    'transaction_type' => 'availability_change',
                    'quantity' => 0,
                    'quantity_delta' => 0,
                    'stock_before' => $stock,
                    'stock_after' => $stock,
                    'reason' => trim($request->input('stock_adjustment_reason')).' ('.$previousAvailability.' → '.$menuItem->availability.')',
                ]);
            }
        });
        if ($menuItem->wasChanged(['availability', 'prepared_stock_quantity'])) {
            event(new MenuAvailabilityChanged(
                (int) $menuItem->branch_id,
                (int) $menuItem->id,
                (string) $menuItem->availability,
                $menuItem->prepared_stock_quantity === null ? null : (int) $menuItem->prepared_stock_quantity,
            ));
        }

        return redirect()->route('menu-items.index')->with('success', 'Menu item updated successfully.');
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        $this->authorizeBranch($menuItem->branch);

        if ($menuItem->kitchenMenuItems()->exists()) {
            return back()->with('error', 'This menu item is mapped to a kitchen and cannot be deleted.');
        }

        $menuItem->delete();

        return redirect()->route('menu-items.index')->with('success', 'Menu item deleted successfully.');
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->authorizeBranch($branch);

        return response()->json($this->optionPayload($this->menuItemChoices($branch->id)));
    }

    private function selectionState(?MenuItem $menuItem = null): array
    {
        $state = $this->formState($menuItem);
        $companyId = $state['selectedCompany'] ? (int) $state['selectedCompany'] : null;
        $branchId = $state['selectedBranch'] ? (int) $state['selectedBranch'] : null;

        return array_merge($state, [
            'categories' => $this->categoryChoices($companyId, $branchId, $menuItem ? (int) old('category_id', $menuItem->category_id) : null),
            'menuTypes' => $this->menuTypeChoices($companyId, $branchId, $menuItem ? (int) old('menu_type_id', $menuItem->menu_type_id) : null),
            'availabilities' => $this->availabilities(),
        ]);
    }

    private function validateMenuItem(Request $request, ?MenuItem $menuItem = null): array
    {
        return $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $menuItem?->company_id)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($request, $menuItem) {
                    $query->where('company_id', $request->input('company_id'));
                    $this->limitToActiveOrCurrent($query, $menuItem?->branch_id);
                }),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($request, $menuItem) {
                    $query->where('company_id', $request->input('company_id'))
                        ->where(function ($query) use ($request) {
                            $query->whereNull('branch_id')->orWhere('branch_id', $request->input('branch_id'));
                        });
                    $this->limitToActiveOrCurrent($query, $menuItem?->category_id);
                }),
            ],
            'menu_type_id' => [
                'required',
                'integer',
                Rule::exists('menu_types', 'id')->where(function ($query) use ($request, $menuItem) {
                    $query->where('company_id', $request->input('company_id'))
                        ->where(function ($query) use ($request) {
                            $query->whereNull('branch_id')->orWhere('branch_id', $request->input('branch_id'));
                        });
                    $this->limitToActiveOrCurrent($query, $menuItem?->menu_type_id);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('menu_items', 'code')->where('branch_id', $request->input('branch_id'))->ignore($menuItem?->id),
            ],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'description' => ['nullable', 'string'],
            'preparation_instructions' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'stock_tracking_enabled' => ['required', 'boolean'],
            'prepared_stock_quantity' => ['required_if:stock_tracking_enabled,1', 'nullable', 'integer', 'min:0', 'max:999999'],
            'availability' => ['required', Rule::in(array_keys($this->availabilities()))],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function availabilities(): array
    {
        return [
            'available' => 'Available',
            'temporarily_unavailable' => 'Temporarily unavailable',
            'sold_out' => 'Sold out',
        ];
    }
}
