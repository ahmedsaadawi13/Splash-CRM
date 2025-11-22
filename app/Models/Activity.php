<?php

namespace App\Models;

class Activity extends BaseModel
{
    protected $table = 'activities';

    protected $fillable = [
        'tenant_id',
        'subject',
        'activity_type',
        'status',
        'priority',
        'due_date',
        'start_datetime',
        'end_datetime',
        'duration_minutes',
        'reminder_datetime',
        'related_to_type',
        'related_to_id',
        'owner_id',
        'assigned_to_id',
        'location',
        'attendees',
        'description',
        'outcome',
        'custom_fields',
    ];

    protected $casts = [
        'attendees' => 'array',
        'custom_fields' => 'array',
        'due_date' => 'date',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'reminder_datetime' => 'datetime',
    ];

    /**
     * Owner relationship
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Assigned to relationship
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Check if activity is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'completed';
    }

    /**
     * Check if activity is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
