<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Tenant model for multi-tenancy support.
 *
 * Links to the existing Jetstream Team model to leverage
 * existing team-based organization structure.
 *
 * @property string $id UUID identifier
 * @property int|null $team_id Link to existing Team
 * @property string $name Tenant display name
 * @property string|null $plan Subscription plan
 * @property \Carbon\Carbon|null $trial_ends_at Trial expiration
 * @property array<string, mixed> $data Additional tenant data (JSON)
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * Get the custom columns for the tenant model.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'team_id',
            'name',
            'plan',
            'trial_ends_at',
        ];
    }

    /**
     * Get the team associated with this tenant.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Create a tenant from an existing team.
     */
    public static function createFromTeam(Team $team): self
    {
        return static::create([
            'team_id' => $team->id,
            'name'    => $team->name,
            'plan'    => 'default',
        ]);
    }
}
