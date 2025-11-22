<?php

/**
 * Invoice Model
 *
 * File: app/Models/Invoice.php
 *
 * Represents a sales invoice with line items and payment tracking
 *
 * @package App\Models
 * @version 1.0.0
 */

namespace App\Models;

class Invoice extends BaseModel
{
    protected $table = 'invoices';

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'invoice_name',
        'quote_id',
        'account_id',
        'contact_id',
        'invoice_date',
        'due_date',
        'paid_date',
        'status',
        'subtotal',
        'tax',
        'shipping',
        'discount',
        'total',
        'amount_paid',
        'balance',
        'payment_terms',
        'owner_id',
        'description',
        'notes',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Quote relationship
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
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
     * Invoice items relationship
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Payments relationship
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'paid';
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partial';
    }

    /**
     * Get remaining balance
     */
    public function getRemainingBalance(): float
    {
        return (float) $this->total - (float) $this->amount_paid;
    }
}
