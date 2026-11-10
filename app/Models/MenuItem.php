<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'category_id',
        'menu_type_id',
        'name',
        'code',
        'selling_price',
        'image',
        'description',
        'preparation_instructions',
        'display_order',
        'stock_tracking_enabled',
        'prepared_stock_quantity',
        'availability',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'display_order' => 'integer',
            'stock_tracking_enabled' => 'boolean',
            'prepared_stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function menuType(): BelongsTo
    {
        return $this->belongsTo(MenuType::class);
    }

    public function kitchenMenuItems(): HasMany
    {
        return $this->hasMany(KitchenMenuItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
