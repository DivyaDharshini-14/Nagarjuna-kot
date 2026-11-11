<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'cancelled_quantity',
        'unit_price',
        'total_price',
        'special_instructions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cancelled_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function kotItems(): HasMany
    {
        return $this->hasMany(KotItem::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(ItemCancellation::class);
    }
}

