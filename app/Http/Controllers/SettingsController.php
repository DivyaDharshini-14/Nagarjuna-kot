<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Services\BranchSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request, BranchSettings $settings)
    {
        $validated = $request->validate(['branch_id' => ['nullable', 'integer', 'min:1']]);
        $branches = $this->applyBranchScope(Branch::query(), 'company_id', 'id')->where('is_active', true)->orderBy('name')->get();
        $selectedId = $this->locksBranch() ? $this->actor()->branch_id : ($validated['branch_id'] ?? $branches->first()?->id);
        $branch = $selectedId ? $branches->firstWhere('id', (int) $selectedId) : null;
        abort_if($selectedId && ! $branch, 404);

        return view('settings.index', [
            'branches' => $branches, 'branch' => $branch,
            'values' => $branch ? $settings->forBranch((int) $branch->id) : BranchSettings::DEFAULTS,
        ]);
    }

    public function update(Request $request, Branch $branch, BranchSettings $settings)
    {
        $this->authorizeBranch($branch);
        abort_unless($branch->is_active, 404);
        $values = $request->validate([
            'order_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'order_digits' => ['required', 'integer', 'min:1', 'max:10'],
            'kot_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'kot_digits' => ['required', 'integer', 'min:1', 'max:10'],
            'kot_daily_reset' => ['required', 'boolean'],
            'sound_enabled' => ['required', 'boolean'],
            'low_stock_threshold' => ['required', 'integer', 'min:1', 'max:99999'],
            'cancellation_approval_required' => ['required', 'boolean'],
        ]);
        foreach (['kot_daily_reset', 'sound_enabled', 'cancellation_approval_required'] as $key) {
            $values[$key] = $request->boolean($key);
        }
        foreach (['order_digits', 'kot_digits', 'low_stock_threshold'] as $key) {
            $values[$key] = (int) $values[$key];
        }

        DB::transaction(function () use ($branch, $settings, $values) {
            $locked = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $before = $settings->forBranch((int) $branch->id);
            $settings->save((int) $branch->company_id, (int) $branch->id, $values);
            $locked->update(['cancellation_approval_required' => $values['cancellation_approval_required']]);
            DB::table('audit_logs')->insert([
                'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'user_id' => $this->actor()->id,
                'action' => 'settings_updated', 'subject_type' => 'branches', 'subject_id' => $branch->id,
                'changes' => json_encode(['before' => $before, 'after' => array_intersect_key($values, BranchSettings::DEFAULTS)], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('settings.index', ['branch_id' => $branch->id])->with('success', 'Branch settings saved. Existing order and ticket numbers are unchanged.');
    }
}
