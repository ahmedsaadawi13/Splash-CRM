<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo',
        'settings',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
        'max_users',
        'max_storage_gb',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /**
     * Users relationship
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if tenant is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenant subscription is valid
     */
    public function hasValidSubscription(): bool
    {
        if (!$this->subscription_ends_at) {
            return true; // No expiration set
        }

        return $this->subscription_ends_at->isFuture();
    }
}
