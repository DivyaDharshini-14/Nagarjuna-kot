<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kitchen extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'description',
        'is_active',
        'display_token_hash',
        'display_token_created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_token_created_at' => 'datetime',
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

    public function kitchenMenuItems(): HasMany
    {
        return $this->hasMany(KitchenMenuItem::class);
    }

    public function kotTickets(): HasMany
    {
        return $this->hasMany(KotTicket::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
