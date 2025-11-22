<?php

namespace App\Models;

class Account extends BaseModel
{
    protected $table = 'accounts';

    protected $fillable = [
        'tenant_id',
        'account_name',
        'account_number',
        'account_type',
        'industry',
        'website',
        'phone',
        'fax',
        'email',
        'annual_revenue',
        'employees',
        'rating',
        'ownership',
        'billing_street',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'parent_account_id',
        'owner_id',
        'description',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'annual_revenue' => 'decimal:2',
    ];

    /**
     * Owner relationship
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Parent account relationship
     */
    public function parentAccount()
    {
        return $this->belongsTo(Account::class, 'parent_account_id');
    }

    /**
     * Child accounts relationship
     */
    public function childAccounts()
    {
        return $this->hasMany(Account::class, 'parent_account_id');
    }

    /**
     * Contacts relationship
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Opportunities relationship
     */
    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
