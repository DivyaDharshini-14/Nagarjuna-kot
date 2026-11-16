<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BranchSettings
{
    public const DEFAULTS = [
        'order_prefix' => 'ORD-',
        'order_digits' => 4,
        'kot_prefix' => 'KOT-',
        'kot_digits' => 1,
        'kot_daily_reset' => true,
        'sound_enabled' => true,
        'low_stock_threshold' => 5,
    ];

    public function forBranch(int $branchId): array
    {
        $values = DB::table('settings')->where('branch_id', $branchId)->pluck('value', 'key');

        return array_replace(self::DEFAULTS, $values->map(fn ($value) => json_decode($value, true, flags: JSON_THROW_ON_ERROR))->all());
    }

    public function save(int $companyId, int $branchId, array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['branch_id' => $branchId, 'key' => $key],
                ['company_id' => $companyId, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
