<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'employee_code',
        'designation',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Company relationship
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Branch relationship
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function kitchens(): BelongsToMany
    {
        return $this->belongsToMany(Kitchen::class)->withTimestamps();
    }

    // User roles relationship
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withTimestamps();
    }

    public function waiterOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }

    protected ?array $cachedRoleSlugs = null;
    protected ?array $cachedPermissions = null;

    public function hasRole(string $role): bool
    {
        if ($this->cachedRoleSlugs === null) {
            $this->cachedRoleSlugs = $this->roles()
                ->where('is_active', true)
                ->pluck('slug')
                ->all();
        }

        return in_array($role, $this->cachedRoleSlugs, true);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super-admin') || $this->hasRole('admin')) {
            return true;
        }

        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = $this->roles()
                ->where('roles.is_active', true)
                ->with('permissions')
                ->get()
                ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
                ->unique()
                ->all();
        }

        return in_array($permission, $this->cachedPermissions, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super-admin');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager') || $this->hasRole('branch-manager') || $this->hasRole('restaurant-manager');
    }

    public function isWaiter(): bool
    {
        return $this->hasRole('waiter') || $this->hasRole('captain-waiter');
    }

    public function isKitchenStaff(): bool
    {
        return $this->hasRole('kitchen-staff') || $this->hasRole('kitchen-manager');
    }

    public function canAuthenticate(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->company_id && ! $this->company()->where('is_active', true)->exists()) {
            return false;
        }

        if ($this->branch_id) {
            return $this->branch()
                ->where('is_active', true)
                ->where('company_id', $this->company_id)
                ->exists();
        }

        return true;
    }
}
