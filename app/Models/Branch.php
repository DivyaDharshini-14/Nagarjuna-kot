<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'email',
        'phone',
        'address',
        'is_active',
        'cancellation_approval_required',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'cancellation_approval_required' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function kitchens(): HasMany
    {
        return $this->hasMany(Kitchen::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function menuTypes(): HasMany
    {
        return $this->hasMany(MenuType::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function kitchenMenuItems(): HasMany
    {
        return $this->hasMany(KitchenMenuItem::class);
    }
}
