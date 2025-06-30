<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToTeam
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToTeam()
    {
        // Automatically set team_id when creating models
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->currentTeam) {
                $model->team_id = Auth::user()->currentTeam->id;
            }
        });
        
        // Global scope to filter by team
        static::addGlobalScope('team', function (Builder $builder) {
            if (Auth::check() && Auth::user()->currentTeam) {
                $builder->where('team_id', Auth::user()->currentTeam->id);
            }
        });
    }
    
    /**
     * Scope to include all teams (bypass team isolation)
     */
    public function scopeAllTeams($query)
    {
        return $query->withoutGlobalScope('team');
    }
    
    /**
     * Scope to filter by specific team
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->withoutGlobalScope('team')->where('team_id', $teamId);
    }
    
    /**
     * Get the team relationship
     */
    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }
}