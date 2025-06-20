<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Values\UserRoles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use App\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            'current_team_id' => null,
            'kyc_status' => 'not_started',
            'kyc_level' => 'basic',
            'pep_status' => false,
            'data_retention_consent' => false,
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            // Default role assignment using RoleFactory
            $role = Role::factory()->withRole(UserRoles::PRIVATE)->make();
            $user->setRelation('roles', collect([$role]));
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withBusinessRole(): static
    {
        return $this->afterMaking(function (User $user) {
            $role = Role::factory()->withRole(UserRoles::BUSINESS)->make();
            $user->setRelation('roles', collect([$role]));
        });
    }

    public function withAdminRole(): static
    {
        return $this->afterMaking(function (User $user) {
            $role = Role::factory()->withRole(UserRoles::ADMIN)->make();
            $user->setRelation('roles', collect([$role]));
        });
    }

    /**
     * Indicate that the user should have a personal team.
     */
    public function withPersonalTeam(?callable $callback = null): static
    {
        if (! Features::hasTeamFeatures()) {
            return $this->state([]);
        }

        return $this->has(
            Team::factory()
                ->state(fn (array $attributes, User $user) => [
                    'name' => $user->name.'\'s Team',
                    'user_id' => $user->id,
                    'personal_team' => true,
                ])
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }
}
