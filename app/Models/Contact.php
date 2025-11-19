<?php

namespace App\Models;

class Contact extends BaseModel
{
    protected $table = 'contacts';

    protected $fillable = [
        'tenant_id',
        'account_id',
        'salutation',
        'first_name',
        'last_name',
        'title',
        'department',
        'email',
        'phone',
        'mobile',
        'fax',
        'mailing_street',
        'mailing_city',
        'mailing_state',
        'mailing_postal_code',
        'mailing_country',
        'birthdate',
        'lead_source',
        'reports_to_id',
        'owner_id',
        'status',
        'description',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'birthdate' => 'date',
    ];

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Account relationship
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Owner relationship
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Reports to relationship
     */
    public function reportsTo()
    {
        return $this->belongsTo(Contact::class, 'reports_to_id');
    }
}
