<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Mobile\Models\MobileDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for MobileDevice model.
 *
 * @extends Factory<MobileDevice>
 */
class MobileDeviceFactory extends Factory
{
    protected $model = MobileDevice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'              => User::factory(),
            'device_id'            => 'device_' . Str::uuid()->toString(),
            'platform'             => $this->faker->randomElement(['ios', 'android']),
            'push_token'           => 'fcm_' . Str::random(150),
            'device_name'          => $this->faker->randomElement(['iPhone 15 Pro', 'Pixel 8', 'Galaxy S24']),
            'device_model'         => $this->faker->randomElement(['iPhone15,3', 'Pixel8', 'SM-S928B']),
            'os_version'           => $this->faker->randomElement(['17.0', '14', '15']),
            'app_version'          => '1.0.0',
            'biometric_enabled'    => false,
            'biometric_public_key' => null,
            'biometric_key_id'     => null,
            'last_active_at'       => now(),
            'is_trusted'           => false,
            'is_blocked'           => false,
            'metadata'             => [],
        ];
    }

    /**
     * Configure device with biometric enabled.
     */
    public function biometricEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'biometric_enabled'    => true,
            'biometric_public_key' => base64_encode(random_bytes(65)), // Fake EC public key
            'biometric_key_id'     => 'key_' . Str::random(16),
            'biometric_enabled_at' => now(),
        ]);
    }

    /**
     * Configure device as trusted.
     */
    public function trusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_trusted' => true,
            'trusted_at' => now(),
        ]);
    }

    /**
     * Configure device as blocked.
     */
    public function blocked(string $reason = 'Security violation'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocked'     => true,
            'blocked_at'     => now(),
            'blocked_reason' => $reason,
        ]);
    }

    /**
     * Configure iOS device.
     */
    public function ios(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform'     => 'ios',
            'device_name'  => 'iPhone 15 Pro',
            'device_model' => 'iPhone15,3',
            'os_version'   => '17.0',
        ]);
    }

    /**
     * Configure Android device.
     */
    public function android(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform'     => 'android',
            'device_name'  => 'Pixel 8 Pro',
            'device_model' => 'Pixel8Pro',
            'os_version'   => '14',
        ]);
    }

    /**
     * Configure device with biometric blocked.
     */
    public function biometricBlocked(int $minutes = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'biometric_blocked_until' => now()->addMinutes($minutes),
            'biometric_failure_count' => 3,
        ]);
    }
}
