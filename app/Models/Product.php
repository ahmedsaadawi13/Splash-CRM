<?php

namespace App\Models;

class Product extends BaseModel
{
    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'product_name',
        'product_code',
        'product_category',
        'product_family',
        'unit_price',
        'cost_price',
        'list_price',
        'sku',
        'quantity_in_stock',
        'reorder_level',
        'active',
        'taxable',
        'vendor_name',
        'description',
        'specifications',
        'custom_fields',
    ];

    protected $casts = [
        'specifications' => 'array',
        'custom_fields' => 'array',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'list_price' => 'decimal:2',
        'active' => 'boolean',
        'taxable' => 'boolean',
    ];

    /**
     * Scope active products
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Check if product is in stock
     */
    public function inStock(): bool
    {
        return $this->quantity_in_stock > 0;
    }

    /**
     * Check if product needs reorder
     */
    public function needsReorder(): bool
    {
        return $this->reorder_level && $this->quantity_in_stock <= $this->reorder_level;
    }
}
