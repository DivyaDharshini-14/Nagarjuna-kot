<?php

namespace App\Observers;

use App\Models\KotItem;
use App\Models\KotTicket;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalAuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored');
    }

    public function relationshipsChanged(Model $model, array $changes): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }
        $changed = collect($changes)->contains(fn ($result) => collect($result)->contains(fn ($ids) => ! empty($ids)));
        if (! $changed) {
            return;
        }
        DB::table('audit_logs')->insert([
            'company_id' => $model->company_id, 'branch_id' => $model->branch_id, 'user_id' => auth()->id(),
            'action' => 'access_updated', 'subject_type' => $model->getTable(), 'subject_id' => $model->id,
            'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function record(Model $model, string $action): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }
        $attributes = $action === 'created' ? $model->getAttributes() : $model->getChanges();
        $attributes = array_diff_key($attributes, array_flip(['password', 'remember_token', 'display_token_hash', 'updated_at']));
        if ($action === 'updated' && $attributes === []) {
            return;
        }
        $order = match (true) {
            $model instanceof Order => $model,
            $model instanceof OrderItem, $model instanceof KotTicket => $model->order,
            $model instanceof KotItem => $model->ticket?->order,
            default => null,
        };
        $companyId = $model->company_id ?? $order?->company_id;
        $branchId = $model->branch_id ?? $order?->branch_id;
        if ($model->getTable() === 'companies') {
            $companyId = $model->id;
        }
        if ($model->getTable() === 'branches') {
            $branchId = $model->id;
        }
        $changes = [];
        foreach ($attributes as $key => $value) {
            $changes[$key] = ['from' => $action === 'created' ? null : $model->getRawOriginal($key), 'to' => $value];
        }
        DB::table('audit_logs')->insert([
            'company_id' => $companyId, 'branch_id' => $branchId, 'user_id' => auth()->id(),
            'action' => $action, 'subject_type' => $model->getTable(), 'subject_id' => $model->id,
            'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
        $statusKey = $model instanceof KotTicket ? 'ticket_status' : ($model instanceof KotItem ? 'item_status' : 'status');
        if ($order && array_key_exists($statusKey, $attributes) && Schema::hasTable('order_status_history')) {
            DB::table('order_status_history')->insert([
                'company_id' => $order->company_id, 'branch_id' => $order->branch_id, 'order_id' => $order->id,
                'user_id' => auth()->id(), 'subject_type' => $model->getTable(), 'subject_id' => $model->id,
                'from_status' => $action === 'created' ? null : $model->getRawOriginal($statusKey),
                'to_status' => $attributes[$statusKey], 'created_at' => now(),
            ]);
        }
    }
}
