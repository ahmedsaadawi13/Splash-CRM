<?php

/**
 * Payment Model
 *
 * File: app/Models/Payment.php
 *
 * Represents a payment against an invoice
 *
 * @package App\Models
 * @version 1.0.0
 */

namespace App\Models;

class Payment extends BaseModel
{
    protected $table = 'payments';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'payment_number',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Invoice relationship
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
