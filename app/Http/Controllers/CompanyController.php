<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $companies = $this->applyCompanyScope(Company::query(), 'id');
        $this->applySearch($companies, $request, ['name', 'code', 'email', 'phone']);
        $this->applyStatus($companies, $request);

        $companies = $companies->latest()->paginate(10)->withQueryString();

        return view('companies.index', compact('companies'));
    }

    public function create(): View
    {
        abort_unless($this->isSuperAdmin(), 403);

        return view('companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $this->validateCompany($request);

        Company::create($validated);

        return redirect()->route('companies.index')->with('success', 'Company created successfully.');
    }

    public function edit(Company $company): View
    {
        $this->authorizeCompanyId((int) $company->id);

        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompanyId((int) $company->id);

        $company->update($this->validateCompany($request, $company));

        return redirect()->route('companies.index')->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorizeCompanyId((int) $company->id);

        if (
            $company->branches()->exists()
            || $company->users()->exists()
            || $company->kitchens()->exists()
            || $company->floors()->exists()
            || $company->restaurantTables()->exists()
            || $company->categories()->exists()
            || $company->menuTypes()->exists()
            || $company->menuItems()->exists()
        ) {
            return back()->with('error', 'This company has related records and cannot be deleted.');
        }

        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Company deleted successfully.');
    }

    private function validateCompany(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('companies', 'code')->ignore($company?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
