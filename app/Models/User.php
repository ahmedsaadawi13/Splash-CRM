<?php

namespace App\Models;

class User extends BaseModel
{
    protected $table = 'users';

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'password_hash',
        'phone',
        'avatar',
        'locale',
        'timezone',
        'status',
        'settings',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'settings' => 'array',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /**
     * Get user's full name
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Tenant relationship
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Roles relationship
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Check if user has role
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })
            ->exists();
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    /**
     * Set password
     */
    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Update last login
     */
    public function updateLastLogin(string $ipAddress): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ipAddress;
        $this->save();
    }
}
