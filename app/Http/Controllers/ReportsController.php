<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Kitchen;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    use ManagesTenantAccess;

    private const TYPES = [
        'orders' => 'Orders', 'kots' => 'KOT tickets', 'kitchens' => 'Kitchen order summary',
        'dish-quantities' => 'Dish order quantities', 'stock-in' => 'Opening stock and stock in',
        'stock-deductions' => 'Stock deductions', 'stock-adjustments' => 'Stock adjustments',
        'wastage' => 'Wastage and damaged dishes', 'cancellations' => 'Cancellations',
        'preparation-time' => 'Kitchen preparation time', 'user-activity' => 'User activity',
    ];

    public function index(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'kitchen_id' => ['nullable', 'integer', 'exists:kitchens,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:100'],
            'export' => ['nullable', 'boolean'],
        ]);
        $type = $filters['type'] ?? 'orders';
        if ($type === 'user-activity') {
            abort_unless($this->actor()->hasPermission('audit-log.view'), 403);
        }
        $filters['date_from'] = $filters['date_from'] ?? now()->toDateString();
        $filters['date_to'] = $filters['date_to'] ?? $filters['date_from'];
        $filters['company_id'] = $this->locksCompany() ? $this->actor()->company_id : ($filters['company_id'] ?? null);
        $filters['branch_id'] = $this->locksBranch() ? $this->actor()->branch_id : ($filters['branch_id'] ?? null);
        if ($filters['company_id']) {
            $this->authorizeCompanyId((int) $filters['company_id']);
        }
        if ($filters['branch_id']) {
            $branch = Branch::withTrashed()->findOrFail($filters['branch_id']);
            $this->authorizeBranch($branch);
            abort_if($filters['company_id'] && (int) $branch->company_id !== (int) $filters['company_id'], 422, 'Choose a branch in the selected company.');
        }
        if (! empty($filters['kitchen_id'])) {
            $kitchen = $this->reportKitchens()->findOrFail($filters['kitchen_id']);
            abort_if(($filters['company_id'] && (int) $kitchen->company_id !== (int) $filters['company_id'])
                || ($filters['branch_id'] && (int) $kitchen->branch_id !== (int) $filters['branch_id']), 422, 'Choose a kitchen in the selected company and branch.');
        }

        [$query, $columns, $description] = $this->report($type, $filters);
        if ($request->boolean('export')) {
            abort_unless($this->actor()->hasPermission('report.export'), 403);
            if ($type === 'user-activity') {
                abort_unless($this->actor()->hasPermission('audit-log.export'), 403);
            }

            return response()->streamDownload(function () use ($query, $columns) {
                $output = fopen('php://output', 'w');
                fputcsv($output, array_values($columns), escape: '');
                foreach ($query->cursor() as $record) {
                    $row = [];
                    foreach ($columns as $key => $label) {
                        $value = (string) ($record->{$key} ?? '');
                        if (! is_numeric($value) && preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
                            $value = "'".$value;
                        }
                        $row[] = $value;
                    }
                    fputcsv($output, $row, escape: '');
                }
                fclose($output);
            }, 'nagarjuna-'.$type.'-'.$filters['date_from'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $records = $query->paginate(30)->withQueryString();
        $types = self::TYPES;
        if (! $this->actor()->hasPermission('audit-log.view')) {
            unset($types['user-activity']);
        }
        $companies = $this->applyCompanyScope(Company::withTrashed(), 'id')->orderBy('name')->get();
        $branches = $this->applyBranchScope(Branch::withTrashed(), 'company_id', 'id')->with(['company' => fn ($query) => $query->withTrashed()])->orderBy('name')->get();
        $kitchens = $this->reportKitchens()->with(['branch' => fn ($query) => $query->withTrashed()])->orderBy('name')->get();

        return view('reports.index', compact('records', 'columns', 'description', 'types', 'type', 'filters', 'companies', 'branches', 'kitchens'));
    }

    private function scoped(Builder $query, string $alias, array $filters): Builder
    {
        if ($this->locksCompany()) {
            $query->where($alias.'.company_id', $this->actor()->company_id);
        }
        if ($this->locksBranch()) {
            $query->where($alias.'.branch_id', $this->actor()->branch_id);
        }
        if (! empty($filters['company_id'])) {
            $query->where($alias.'.company_id', $filters['company_id']);
        }
        if (! empty($filters['branch_id'])) {
            $query->where($alias.'.branch_id', $filters['branch_id']);
        }

        return $query->whereBetween($alias.'.created_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59']);
    }

    private function reportKitchens(): \Illuminate\Database\Eloquent\Builder
    {
        // Historical reporting must retain disabled and archived stations.
        $query = Kitchen::withTrashed();
        $this->applyBranchScope($query);
        if ($this->actor()->isKitchenStaff() && ! $this->isSuperAdmin()) {
            $query->whereHas('users', fn ($users) => $users->where('users.id', $this->actor()->id));
        }

        return $query;
    }

    private function orderFilter(Builder $query, array $filters, string $order = 'o'): void
    {
        if (! empty($filters['order_number'])) {
            $query->where($order.'.order_number', 'like', '%'.$filters['order_number'].'%');
        }
    }

    private function kitchenFilter(Builder $query, array $filters, string $orderColumn = 'o.id', ?string $itemColumn = null): void
    {
        if (empty($filters['kitchen_id']) && ! $this->actor()->isKitchenStaff()) {
            return;
        }
        $kitchenIds = $this->reportKitchens()->select('kitchens.id');
        if (! empty($filters['kitchen_id'])) {
            $kitchenIds->whereKey($filters['kitchen_id']);
        }
        $query->whereExists(function ($tickets) use ($orderColumn, $itemColumn, $kitchenIds) {
            $tickets->selectRaw('1')->from('kot_tickets as scope_ticket')
                ->whereColumn('scope_ticket.order_id', $orderColumn)->whereNull('scope_ticket.deleted_at')
                ->whereIn('scope_ticket.kitchen_id', $kitchenIds);
            if ($itemColumn) {
                $tickets->whereExists(fn ($items) => $items->selectRaw('1')->from('kot_items as scope_item')
                    ->whereColumn('scope_item.kot_ticket_id', 'scope_ticket.id')->whereColumn('scope_item.order_item_id', $itemColumn));
            }
        });
    }

    private function report(string $type, array $filters): array
    {
        $organization = ['company' => 'Company', 'branch' => 'Branch'];
        if ($type === 'orders' || $type === 'dish-quantities') {
            $query = DB::table('orders as o')->join('companies as company', 'company.id', '=', 'o.company_id')
                ->join('branches as branch', 'branch.id', '=', 'o.branch_id')->whereNull('o.deleted_at');
            $this->scoped($query, 'o', $filters);
            $this->orderFilter($query, $filters);
            if ($type === 'orders') {
                $query->leftJoin('restaurant_tables as dining', 'dining.id', '=', 'o.table_id')
                    ->leftJoin('users as waiter', 'waiter.id', '=', 'o.waiter_id');
                $this->kitchenFilter($query, $filters);
                if (! empty($filters['status'])) {
                    $query->where('o.status', $filters['status']);
                }
                $query->select('company.name as company', 'branch.name as branch', 'o.order_number', 'dining.name as table_name', 'waiter.name as waiter', 'o.status', 'o.created_at')
                    ->selectSub(DB::table('order_items')->selectRaw('COALESCE(SUM(quantity - cancelled_quantity), 0)')->whereColumn('order_id', 'o.id'), 'active_quantity')->latest('o.id');

                return [$query, $organization + ['order_number' => 'Order', 'table_name' => 'Table', 'waiter' => 'Waiter', 'active_quantity' => 'Active portions', 'status' => 'Status', 'created_at' => 'Ordered at'], 'Orders created in the selected date range. Active portions exclude approved cancellations.'];
            }
            $query->join('order_items as i', 'i.order_id', '=', 'o.id')->join('menu_items as dish', 'dish.id', '=', 'i.menu_item_id')->whereNull('i.deleted_at');
            $this->kitchenFilter($query, $filters, 'o.id', 'i.id');
            if (! empty($filters['status'])) {
                $query->where('i.status', $filters['status']);
            }
            $query->select('company.name as company', 'branch.name as branch', 'dish.code', 'dish.name as dish')
                ->selectRaw('SUM(i.quantity) as ordered_quantity, SUM(i.cancelled_quantity) as cancelled_quantity, SUM(i.quantity - i.cancelled_quantity) as active_quantity')
                ->groupBy('company.id', 'company.name', 'branch.id', 'branch.name', 'dish.id', 'dish.code', 'dish.name')->orderByDesc('active_quantity')->orderBy('dish.id');

            return [$query, $organization + ['code' => 'Dish code', 'dish' => 'Dish', 'ordered_quantity' => 'Ordered portions', 'cancelled_quantity' => 'Cancelled portions', 'active_quantity' => 'Active portions'], 'Counts each order line once, including dishes handled by several kitchens. The date range refers to order creation.'];
        }
        if (in_array($type, ['kots', 'kitchens', 'preparation-time'], true)) {
            $query = DB::table('kot_tickets as t')->join('orders as o', 'o.id', '=', 't.order_id')
                ->join('kitchens as kitchen', 'kitchen.id', '=', 't.kitchen_id')->join('companies as company', 'company.id', '=', 't.company_id')
                ->join('branches as branch', 'branch.id', '=', 't.branch_id')->whereNull('t.deleted_at')->whereNull('o.deleted_at');
            $this->scoped($query, 't', $filters);
            $this->orderFilter($query, $filters);
            $query->whereIn('t.kitchen_id', $this->reportKitchens()->select('kitchens.id'));
            if (! empty($filters['kitchen_id'])) {
                $query->where('t.kitchen_id', $filters['kitchen_id']);
            }
            if (! empty($filters['status'])) {
                $query->where('t.ticket_status', $filters['status']);
            }
            if ($type === 'kitchens') {
                $query->select('company.name as company', 'branch.name as branch', 'kitchen.name as kitchen')
                    ->selectRaw("COUNT(DISTINCT o.id) as orders, COUNT(t.id) as tickets, SUM(CASE WHEN t.ticket_status IN ('new','accepted','preparing') THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN t.ticket_status = 'ready' THEN 1 ELSE 0 END) as ready, SUM(CASE WHEN t.ticket_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled, ROUND(AVG(t.duration_seconds), 1) as average_seconds")
                    ->groupBy('company.id', 'company.name', 'branch.id', 'branch.name', 'kitchen.id', 'kitchen.name')->orderBy('kitchen.name');

                return [$query, $organization + ['kitchen' => 'Kitchen', 'orders' => 'Orders', 'tickets' => 'KOTs', 'pending' => 'Pending', 'ready' => 'Ready', 'cancelled' => 'Cancelled KOTs', 'average_seconds' => 'Average prep seconds'], 'Kitchen totals for KOTs created in the selected date range. Average uses tickets with a recorded ready duration.'];
            }
            $query->select('company.name as company', 'branch.name as branch', 'kitchen.name as kitchen', 't.kot_number', 'o.order_number', 't.ticket_status as status', 't.created_at', 't.accepted_at', 't.preparing_at', 't.ready_at', 't.duration_seconds')->latest('t.id');
            $columns = $organization + ['kitchen' => 'Kitchen', 'kot_number' => 'KOT', 'order_number' => 'Order', 'status' => 'Status', 'created_at' => 'Received at'];
            if ($type === 'preparation-time') {
                $columns += ['accepted_at' => 'Accepted at', 'preparing_at' => 'Preparation started', 'ready_at' => 'Ready at', 'duration_seconds' => 'Prep seconds'];
            }

            return [$query, $columns, 'KOTs created in the selected date range. Preparation duration runs from acceptance (or receipt when unavailable) to ready; unfinished tickets have no completed duration.'];
        }
        if ($type === 'cancellations') {
            $query = DB::table('item_cancellations as c')->join('orders as o', 'o.id', '=', 'c.order_id')
                ->join('order_items as i', 'i.id', '=', 'c.order_item_id')->join('menu_items as dish', 'dish.id', '=', 'i.menu_item_id')
                ->join('companies as company', 'company.id', '=', 'c.company_id')->join('branches as branch', 'branch.id', '=', 'c.branch_id')
                ->leftJoin('users as requester', 'requester.id', '=', 'c.requested_by')->leftJoin('users as approver', 'approver.id', '=', 'c.decided_by');
            $this->scoped($query, 'c', $filters);
            $this->orderFilter($query, $filters);
            $this->kitchenFilter($query, $filters, 'o.id', 'i.id');
            if (! empty($filters['status'])) {
                $query->where('c.status', $filters['status']);
            }
            $query->select('company.name as company', 'branch.name as branch', 'o.order_number', 'dish.name as dish', 'c.quantity', 'c.status', 'c.reason', 'requester.name as requester', 'approver.name as approver', 'c.stock_reversed_quantity', 'c.wastage_quantity', 'c.created_at', 'c.decided_at')->latest('c.id');

            return [$query, $organization + ['order_number' => 'Order', 'dish' => 'Dish', 'quantity' => 'Requested quantity', 'status' => 'Decision', 'reason' => 'Reason', 'requester' => 'Requester', 'approver' => 'Decision by', 'stock_reversed_quantity' => 'Returned stock', 'wastage_quantity' => 'Wastage', 'created_at' => 'Requested at', 'decided_at' => 'Decided at'], 'Cancellation requests created in the date range, including pending and rejected requests. Stock returns and wastage apply only to approved cancellations.'];
        }
        if ($type === 'user-activity') {
            $query = DB::table('audit_logs as a')->leftJoin('companies as company', 'company.id', '=', 'a.company_id')
                ->leftJoin('branches as branch', 'branch.id', '=', 'a.branch_id')->leftJoin('users as actor', 'actor.id', '=', 'a.user_id');
            $this->scoped($query, 'a', $filters);
            if (! empty($filters['action'])) {
                $query->where('a.action', $filters['action']);
            }
            if (! empty($filters['order_number']) || ! empty($filters['kitchen_id']) || ! empty($filters['status'])) {
                abort(422, 'Order, kitchen and status filters do not apply to the user activity report. Use the action filter.');
            }
            $query->select('company.name as company', 'branch.name as branch', 'actor.name as user', 'a.action', 'a.subject_type', 'a.subject_id', 'a.created_at')->latest('a.id');

            return [$query, $organization + ['user' => 'User', 'action' => 'Action', 'subject_type' => 'Record type', 'subject_id' => 'Record ID', 'created_at' => 'Occurred at'], 'Recorded critical operations in the selected date range. Activity begins when audit capture is deployed; earlier actions are not reconstructed.'];
        }

        $types = match ($type) {
            'stock-in' => ['opening_stock', 'stock_in'], 'stock-deductions' => ['order_deduction'],
            'wastage' => ['wastage', 'damaged'],
            default => ['manual_adjustment', 'wastage', 'damaged', 'availability_change', 'order_cancellation_reversal'],
        };
        $query = DB::table('stock_transactions as s')->join('menu_items as dish', 'dish.id', '=', 's.menu_item_id')
            ->join('companies as company', 'company.id', '=', 's.company_id')->join('branches as branch', 'branch.id', '=', 's.branch_id')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')->leftJoin('users as actor', 'actor.id', '=', 's.user_id')
            ->whereIn('s.transaction_type', $types);
        $this->scoped($query, 's', $filters);
        $this->orderFilter($query, $filters);
        if (! empty($filters['status'])) {
            $query->where('o.status', $filters['status']);
        }
        if (! empty($filters['kitchen_id']) || $this->actor()->isKitchenStaff()) {
            $kitchens = $this->reportKitchens()->select('kitchens.id');
            if (! empty($filters['kitchen_id'])) {
                $kitchens->whereKey($filters['kitchen_id']);
            }
            $query->whereExists(fn ($mapping) => $mapping->selectRaw('1')->from('kitchen_menu_items as mapping')
                ->whereColumn('mapping.menu_item_id', 's.menu_item_id')->whereIn('mapping.kitchen_id', $kitchens)
                ->whereNull('mapping.deleted_at')->where('mapping.is_active', true));
        }
        $query->select('company.name as company', 'branch.name as branch', 'dish.code', 'dish.name as dish', 's.transaction_type', 's.quantity', 's.quantity_delta', 's.stock_before', 's.stock_after', 'o.order_number', 'actor.name as user', 's.reason', 's.created_at')->latest('s.id');

        return [$query, $organization + ['code' => 'Dish code', 'dish' => 'Dish', 'transaction_type' => 'Movement', 'quantity' => 'Quantity', 'quantity_delta' => 'Stock change', 'stock_before' => 'Before', 'stock_after' => 'After', 'order_number' => 'Order', 'user' => 'User', 'reason' => 'Reason', 'created_at' => 'Recorded at'], 'Stock movements recorded in the selected date range. Kitchen filtering uses the current dish mapping; stock is shared per branch dish. Zero-delta cancellation wastage records explain already-consumed portions.'];
    }
}
