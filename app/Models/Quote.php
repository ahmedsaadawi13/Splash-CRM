<?php

/**
 * Quote Model
 *
 * File: app/Models/Quote.php
 *
 * Represents a sales quote with line items
 *
 * @package App\Models
 * @version 1.0.0
 */

namespace App\Models;

class Quote extends BaseModel
{
    protected $table = 'quotes';

    protected $fillable = [
        'tenant_id',
        'quote_number',
        'quote_name',
        'opportunity_id',
        'account_id',
        'contact_id',
        'quote_date',
        'expiration_date',
        'status',
        'subtotal',
        'tax',
        'shipping',
        'discount',
        'total',
        'payment_terms',
        'owner_id',
        'description',
        'terms_and_conditions',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'quote_date' => 'date',
        'expiration_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Opportunity relationship
     */
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * Account relationship
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Contact relationship
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Owner relationship
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Quote items relationship
     */
    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * Check if quote is expired
     */
    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    /**
     * Check if quote is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if quote is declined
     */
    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }
}
