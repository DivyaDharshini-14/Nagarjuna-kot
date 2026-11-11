<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'menu_item_id', 'user_id', 'order_id',
        'item_cancellation_id', 'transaction_type', 'quantity', 'quantity_delta',
        'stock_before', 'stock_after', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_delta' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(ItemCancellation::class, 'item_cancellation_id');
    }
}
