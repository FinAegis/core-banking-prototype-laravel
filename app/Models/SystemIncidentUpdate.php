<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemIncidentUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_incident_id',
        'status',
        'message',
    ];

    /**
     * Get the incident this update belongs to
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(SystemIncident::class, 'system_incident_id');
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'resolved' => 'green',
            'in_progress' => 'yellow',
            'identified' => 'red',
            default => 'gray',
        };
    }
}