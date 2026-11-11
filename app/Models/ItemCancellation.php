<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCancellation extends Model
{
    protected $fillable = [
        'request_batch', 'company_id', 'branch_id', 'order_id', 'order_item_id',
        'quantity', 'reason', 'status', 'requested_by', 'decided_by', 'decided_at',
        'decision_note', 'stock_reversed', 'stock_reversed_quantity', 'wastage_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'decided_at' => 'datetime',
            'stock_reversed' => 'boolean',
            'stock_reversed_quantity' => 'integer',
            'wastage_quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
