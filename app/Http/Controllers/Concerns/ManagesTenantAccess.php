<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Kitchen;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;



trait ManagesTenantAccess
{
    protected function actor(): User
    {
        return auth()->user();
    }

    protected function isSuperAdmin(): bool
    {
        return $this->actor()->hasRole('super-admin') || $this->actor()->hasRole('admin');
    }

    protected function isManager(): bool
    {
        return $this->actor()->hasRole('manager') || $this->actor()->hasRole('branch-manager') || $this->actor()->hasRole('restaurant-manager');
    }

    protected function isCompanyAdmin(): bool
    {
        return $this->actor()->hasRole('company-admin');
    }

    protected function locksCompany(): bool
    {
        return ! $this->isSuperAdmin();
    }

    protected function locksBranch(): bool
    {
        return ! $this->isSuperAdmin()
            && ! $this->isCompanyAdmin()
            && (bool) $this->actor()->branch_id;
    }

    protected function mergeLockedTenant(Request $request): void
    {
        $user = $this->actor();

        if ($this->locksCompany()) {
            $request->merge(['company_id' => $user->company_id]);
        }

        if ($this->locksBranch()) {
            $request->merge(['branch_id' => $user->branch_id]);
        }
    }

    protected function authorizeCompanyId(int $companyId): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        abort_unless((int) $companyId === (int) $this->actor()->company_id, 403);
    }

    protected function authorizeBranch(Branch $branch): void
    {
        $this->authorizeCompanyId((int) $branch->company_id);

        if ($this->locksBranch()) {
            abort_unless((int) $branch->id === (int) $this->actor()->branch_id, 403);
        }
    }

    protected function assertTenant(array $data, bool $branchRequired = true): ?Branch
    {
        $this->authorizeCompanyId((int) $data['company_id']);

        if (! $branchRequired && empty($data['branch_id'])) {
            abort_if($this->locksBranch(), 403);

            return null;
        }

        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless((int) $branch->company_id === (int) $data['company_id'], 403);
        $this->authorizeBranch($branch);

        return $branch;
    }

    protected function applyCompanyScope(Builder $query, string $column = 'company_id'): Builder
    {
        if ($this->isSuperAdmin()) {
            return $query;
        }

        return $query->where($column, $this->actor()->company_id);
    }

    protected function applyBranchScope(Builder $query, string $companyColumn = 'company_id', ?string $branchColumn = null): Builder
    {
        $this->applyCompanyScope($query, $companyColumn);

        if ($this->locksBranch()) {
            $column = $branchColumn ?? ($query->getModel() instanceof Branch ? 'id' : 'branch_id');
            $query->where($column, $this->actor()->branch_id);
        }

        return $query;
    }

    protected function applySearch(Builder $query, Request $request, array $columns): Builder
    {
        $term = trim((string) $request->search);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($columns, $term) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    protected function applyStatus(Builder $query, Request $request): Builder
    {
        if ($request->status === 'active') {
            $query->where('is_active', true);
        }

        if ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    protected function formState(?object $record = null): array
    {
        $selectedCompany = old('company_id', $record->company_id ?? ($this->locksCompany() ? $this->actor()->company_id : null));
        $selectedBranch = old('branch_id', $record->branch_id ?? ($this->locksBranch() ? $this->actor()->branch_id : null));

        return [
            'companies' => $this->companyChoices($selectedCompany ? (int) $selectedCompany : null),
            'branches' => $selectedCompany ? $this->branchChoices((int) $selectedCompany, $selectedBranch ? (int) $selectedBranch : null) : collect(),
            'lockCompany' => $this->locksCompany(),
            'lockBranch' => $this->locksBranch(),
            'selectedCompany' => $selectedCompany,
            'selectedBranch' => $selectedBranch,
        ];
    }

    protected function companyChoices(?int $selected = null): Collection
    {
        return $this->applyCompanyScope(Company::query(), 'id')
            ->where(function (Builder $query) use ($selected) {
                $query->where('is_active', true);

                if ($selected) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('name')
            ->get();
    }

    protected function branchChoices(?int $companyId, ?int $selected = null): Collection
    {
        if (! $companyId) {
            return collect();
        }

        $this->authorizeCompanyId($companyId);

        return $this->applyBranchScope(Branch::query(), 'company_id', 'id')
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($selected) {
                $query->where('is_active', true);

                if ($selected) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('name')
            ->get();
    }

    protected function floorChoices(?int $branchId, ?int $selected = null): Collection
    {
        if (! $branchId) {
            return collect();
        }

        return $this->applyBranchScope(Floor::query())
            ->where('branch_id', $branchId)
            ->where(function (Builder $query) use ($selected) {
                $query->where('is_active', true);

                if ($selected) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('name')
            ->get();
    }

    protected function kitchenChoices(?int $branchId, ?int $selected = null): Collection
    {
        if (! $branchId) {
            return collect();
        }

        return $this->applyBranchScope(Kitchen::query())
            ->where('branch_id', $branchId)
            ->where(function (Builder $query) use ($selected) {
                $query->where('is_active', true);

                if ($selected) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('name')
            ->get();
    }

    protected function categoryChoices(?int $companyId, ?int $branchId = null, ?int $selected = null): Collection
    {
        if (! $companyId) {
            return collect();
        }

        $this->authorizeCompanyId($companyId);

        if ($this->locksBranch()) {
            $branchId = (int) $this->actor()->branch_id;
        }

        return $this->applyCompanyScope(Category::query())
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchId) {
                $query->whereNull('branch_id');

                if ($branchId) {
                    $query->orWhere('branch_id', $branchId);
                }
            })
            ->where(function (Builder $query) use ($selected) {
                $query->where('is_active', true);

                if ($selected) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    protected function menuTypeChoices(?int $companyId, ?int $branchId = null, ?int $selected = null): Collection
    {
        if (! $companyId) {
            return collect();
        }

        $this->authorizeCompanyId($companyId);

        if ($this->locksBranch()) {
            $branchId = (int) $this->actor()->branch_id;
        }

        return $this->applyCompanyScope(MenuType::query())
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchId) {
                $query->whereNull('branch_id');

                if ($branchId) {
                    $query->orWhere('branch_id', $branchId);
                }
            })
            ->where(function (Builder $query) use ($selected) {
                $query->where('is_active', true);

                if ($selected) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('name')
            ->get();
    }

    protected function menuItemChoices(?int $branchId, ?int $selected = null): Collection
    {
        if (! $branchId) {
            return collect();
        }

        return $this->applyBranchScope(MenuItem::query())
            ->where('branch_id', $branchId)
            ->where(function (Builder $query) use ($selected) {
                $query->where('is_active', true);

                if ($selected) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    protected function optionPayload(Collection $records): array
    {
        return $records->map(fn ($record) => [
            'id' => $record->id,
            'label' => $record->name,
        ])->values()->all();
    }

    protected function limitToActiveOrCurrent(
        \Illuminate\Database\Query\Builder $query,
        ?int $currentId
    ): void {
        $query->whereNull('deleted_at')->where(function ($query) use ($currentId) {
            $query->where('is_active', true);

            if ($currentId) {
                $query->orWhere('id', $currentId);
            }
        });
    }
}
