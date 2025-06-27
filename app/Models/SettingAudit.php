<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_id',
        'key',
        'old_value',
        'new_value',
        'changed_by',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'old_value' => 'json',
        'new_value' => 'json',
        'metadata' => 'json',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }
}