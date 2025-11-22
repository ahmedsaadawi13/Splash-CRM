<?php

namespace App\Models;

class Role extends BaseModel
{
    protected $table = 'roles';

    public $timestamps = true;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'is_system',
    ];

    /**
     * Permissions relationship
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Users relationship
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }
}
