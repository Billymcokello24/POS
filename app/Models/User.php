<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'current_business_id',
        'is_super_admin',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Role-based access control helpers (Dynamic)
    public function isAdmin(): bool
    {
        return $this->is_super_admin || $this->hasRole('admin');
    }

    public function isCashier(): bool
    {
        // A "Cashier" is anyone with level < 75 (Manager/Admin level)
        // OR we can check for a specific permission they LACK
        $role = $this->roles()->wherePivot('business_id', $this->current_business_id)->first();
        return $role ? $role->level < 75 : true; 
    }

    public function isAuditor(): bool
    {
        // An "Auditor" is anyone with view_reports but maybe not edit_settings
        return $this->hasPermission('view_reports') && !$this->hasPermission('edit_settings');
    }

    public function canManageUsers(): bool
    {
        return $this->hasPermission('create_users');
    }

    public function canViewAllSales(): bool
    {
        return $this->hasPermission('view_sales');
    }

    public function canViewOwnSales(): bool
    {
        return $this->hasPermission('view_sales'); // Every role with view_sales can see their own
    }

    public function currentBusiness()
    {
        return $this->belongsTo(Business::class, 'current_business_id');
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'role_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('business_id')
            ->withTimestamps();
    }

    public function hasRole(string $roleName, ?int $businessId = null): bool
    {
        $businessId = $businessId ?? $this->current_business_id;

        return $this->roles()
            ->where('name', $roleName)
            ->wherePivot('business_id', $businessId)
            ->exists();
    }

    public function hasPermission(string $permissionName, ?int $businessId = null): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $businessId = $businessId ?? $this->current_business_id;

        return $this->roles()
            ->wherePivot('business_id', $businessId)
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    public function assignRole(string $roleName, int $businessId): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $this->roles()->syncWithoutDetaching([
            $role->id => ['business_id' => $businessId]
        ]);
    }
}
