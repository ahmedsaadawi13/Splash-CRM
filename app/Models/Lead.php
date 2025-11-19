<?php

namespace App\Models;

class Lead extends BaseModel
{
    protected $table = 'leads';

    protected $fillable = [
        'tenant_id',
        'salutation',
        'first_name',
        'last_name',
        'title',
        'company',
        'email',
        'phone',
        'mobile',
        'fax',
        'website',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'industry',
        'lead_source',
        'lead_status',
        'rating',
        'annual_revenue',
        'employees',
        'converted',
        'converted_at',
        'converted_account_id',
        'converted_contact_id',
        'converted_opportunity_id',
        'owner_id',
        'score',
        'description',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'converted' => 'boolean',
        'converted_at' => 'datetime',
        'annual_revenue' => 'decimal:2',
    ];

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Owner relationship
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Converted account relationship
     */
    public function convertedAccount()
    {
        return $this->belongsTo(Account::class, 'converted_account_id');
    }

    /**
     * Converted contact relationship
     */
    public function convertedContact()
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id');
    }

    /**
     * Converted opportunity relationship
     */
    public function convertedOpportunity()
    {
        return $this->belongsTo(Opportunity::class, 'converted_opportunity_id');
    }

    /**
     * Check if lead is converted
     */
    public function isConverted(): bool
    {
        return $this->converted === true;
    }
}
