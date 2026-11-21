<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KotItem extends Model
{
    public const PENDING_STATUSES = ['new', 'accepted', 'preparing'];

    protected $fillable = [
        'kot_ticket_id',
        'order_item_id',
        'menu_item_id',
        'quantity',
        'cancelled_quantity',
        'special_instructions',
        'item_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cancelled_quantity' => 'integer',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('item_status', self::PENDING_STATUSES)
            ->whereColumn('cancelled_quantity', '<', 'quantity');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KotTicket::class, 'kot_ticket_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}

