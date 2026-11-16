<?php

namespace App\Services;

use App\Models\KotTicket;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DocumentNumbers
{
    public function order(int $branchId): string
    {
        $settings = app(BranchSettings::class)->forBranch($branchId);

        return $this->allocate('orders:global', function () {
            return $this->highestSuffix(Order::withTrashed()->select('order_number')->cursor()->map(fn ($order) => $order->order_number));
        }, function ($number) use ($settings) {
            $candidate = $settings['order_prefix'].str_pad((string) $number, (int) $settings['order_digits'], '0', STR_PAD_LEFT);

            return Order::withTrashed()->where('order_number', $candidate)->exists() ? null : $candidate;
        });
    }

    public function kotPeriod(int $branchId): string
    {
        return app(BranchSettings::class)->forBranch($branchId)['kot_daily_reset'] ? now()->toDateString() : 'all';
    }

    public function kot(int $branchId, string $period): string
    {
        $settings = app(BranchSettings::class)->forBranch($branchId);
        $existing = fn () => KotTicket::withTrashed()->where('branch_id', $branchId)
            ->when($period !== 'all', fn ($query) => $query->whereDate('created_at', $period));

        return $this->allocate('kots:'.$branchId.':'.$period,
            fn () => $this->highestSuffix($existing()->select('kot_number')->cursor()->map(fn ($ticket) => $ticket->kot_number)),
            function ($number) use ($settings, $existing) {
                $candidate = $settings['kot_prefix'].str_pad((string) $number, (int) $settings['kot_digits'], '0', STR_PAD_LEFT);

                return $existing()->where('kot_number', $candidate)->exists() ? null : $candidate;
            },
        );
    }

    private function highestSuffix(iterable $numbers): int
    {
        $highest = 0;
        foreach ($numbers as $number) {
            if (preg_match('/(\d+)$/', (string) $number, $matches)) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return $highest;
    }

    private function allocate(string $scope, callable $initialValue, callable $candidate): string
    {
        // This lock is held until the enclosing order transaction commits, so
        // create and add-items requests use the same atomic sequence.
        return DB::transaction(function () use ($scope, $initialValue, $candidate) {
            DB::table('document_sequences')->insertOrIgnore([
                'scope' => $scope, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $sequence = DB::table('document_sequences')->where('scope', $scope)->lockForUpdate()->first();
            $number = (int) $sequence->last_number;
            if ($number === 0) {
                $number = $initialValue();
            }
            do {
                $number++;
                $value = $candidate($number);
            } while ($value === null);
            DB::table('document_sequences')->where('id', $sequence->id)->update(['last_number' => $number, 'updated_at' => now()]);

            return $value;
        });
    }
}
