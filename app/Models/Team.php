<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'is_business_organization',
        'organization_type',
        'business_registration_number',
        'tax_id',
        'business_details',
        'max_users',
        'allowed_roles',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
            'is_business_organization' => 'boolean',
            'business_details' => 'array',
            'allowed_roles' => 'array',
        ];
    }
    
    /**
     * Team-specific user roles
     */
    public function teamUserRoles()
    {
        return $this->hasMany(TeamUserRole::class);
    }
    
    /**
     * Get team-specific role for a user
     */
    public function getUserTeamRole($user)
    {
        return $this->teamUserRoles()
            ->where('user_id', $user->id)
            ->first();
    }
    
    /**
     * Assign a team-specific role to a user
     */
    public function assignUserRole($user, $role, $permissions = null)
    {
        return $this->teamUserRoles()->updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'permissions' => $permissions,
            ]
        );
    }
    
    /**
     * Check if team has reached user limit
     */
    public function hasReachedUserLimit(): bool
    {
        return $this->users()->count() >= $this->max_users;
    }
    
    /**
     * Available roles for this team
     */
    public function getAvailableRoles(): array
    {
        if (!$this->is_business_organization) {
            return [];
        }
        
        return $this->allowed_roles ?? [
            'compliance_officer',
            'accountant',
            'operations_manager',
            'customer_service',
        ];
    }
}
