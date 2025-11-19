<?php

namespace App\Models;

class Opportunity extends BaseModel
{
    protected $table = 'opportunities';

    protected $fillable = [
        'tenant_id',
        'opportunity_name',
        'account_id',
        'contact_id',
        'amount',
        'stage',
        'probability',
        'expected_close_date',
        'closed_at',
        'opportunity_type',
        'lead_source',
        'status',
        'loss_reason',
        'owner_id',
        'forecast_category',
        'description',
        'next_step',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'amount' => 'decimal:2',
        'expected_close_date' => 'date',
        'closed_at' => 'datetime',
    ];

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
     * Check if opportunity is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if opportunity is won
     */
    public function isWon(): bool
    {
        return $this->status === 'won';
    }

    /**
     * Check if opportunity is lost
     */
    public function isLost(): bool
    {
        return $this->status === 'lost';
    }
}
