<?php

/**
 * Quote Item Model
 *
 * File: app/Models/QuoteItem.php
 *
 * Represents a line item in a quote
 *
 * @package App\Models
 * @version 1.0.0
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $table = 'quote_items';

    protected $fillable = [
        'quote_id',
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
     * Quote relationship
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Product relationship
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
