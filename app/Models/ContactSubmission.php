<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'priority',
        'attachment_path',
        'ip_address',
        'user_agent',
        'status',
        'responded_at',
        'response_notes',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * Get subject label
     */
    public function getSubjectLabelAttribute()
    {
        $labels = [
            'account' => 'Account Issues',
            'technical' => 'Technical Support',
            'billing' => 'Billing & Payments',
            'gcu' => 'GCU Questions',
            'api' => 'API & Integration',
            'compliance' => 'Compliance & Security',
            'other' => 'Other',
        ];

        return $labels[$this->subject] ?? 'Unknown';
    }

    /**
     * Get priority color
     */
    public function getPriorityColorAttribute()
    {
        $colors = [
            'low' => 'gray',
            'medium' => 'yellow',
            'high' => 'orange',
            'urgent' => 'red',
        ];

        return $colors[$this->priority] ?? 'gray';
    }

    /**
     * Mark as responded
     */
    public function markAsResponded($notes = null)
    {
        $this->update([
            'status' => 'responded',
            'responded_at' => now(),
            'response_notes' => $notes,
        ]);
    }
}