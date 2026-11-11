<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KotTicket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kot_number',
        'order_id',
        'kitchen_id',
        'company_id',
        'branch_id',
        'ticket_status',
        'accepted_at',
        'preparing_at',
        'ready_at',
        'duration_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KotItem::class);
    }
}

