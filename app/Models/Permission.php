<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    /**
     * Roles relationship
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
