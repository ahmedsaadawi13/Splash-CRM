<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

abstract class BaseModel extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }

            // Auto-assign tenant_id if not set
            if (property_exists($model, 'tenant_id') && empty($model->tenant_id)) {
                $model->tenant_id = self::getCurrentTenantId();
            }
        });
    }

    /**
     * Get current tenant ID from context
     */
    protected static function getCurrentTenantId(): ?int
    {
        // This would be set by middleware based on authenticated user
        return $_ENV['CURRENT_TENANT_ID'] ?? null;
    }

    /**
     * Scope to current tenant
     */
    public function scopeTenant($query)
    {
        $tenantId = self::getCurrentTenantId();

        if ($tenantId && in_array('tenant_id', $this->fillable)) {
            return $query->where('tenant_id', $tenantId);
        }

        return $query;
    }
}
