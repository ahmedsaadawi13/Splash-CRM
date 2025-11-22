<?php

/**
 * Invoice Item Model
 *
 * File: app/Models/InvoiceItem.php
 *
 * Represents a line item in an invoice
 *
 * @package App\Models
 * @version 1.0.0
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'product_id',
        'line_number',
        'product_name',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /**
     * Invoice relationship
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Product relationship
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
