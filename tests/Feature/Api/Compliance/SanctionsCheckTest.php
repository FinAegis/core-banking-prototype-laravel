<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Compliance;

use App\Domain\Compliance\Contracts\SanctionsScreeningInterface;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class SanctionsCheckTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write', 'delete'])->plainTextToken;
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/compliance/check-address?address=0x1234567890abcdef1234567890abcdef12345678');

        $response->assertUnauthorized();
    }

    public function test_validates_address_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/compliance/check-address');

        $response->assertUnprocessable();
    }

    public function test_returns_screening_result(): void
    {
        $mock = $this->mock(SanctionsScreeningInterface::class);
        $mock->shouldReceive('screenAddress')
            ->with('0x1234567890abcdef1234567890abcdef12345678', 'ethereum')
            ->once()
            ->andReturn([
                'matches'       => [],
                'lists_checked' => ['OFAC SDN', 'EU Sanctions'],
                'total_matches' => 0,
            ]);
        $mock->shouldReceive('getName')->andReturn('test_provider');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/compliance/check-address?address=0x1234567890abcdef1234567890abcdef12345678');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sanctioned', false)
            ->assertJsonPath('data.risk_score', 'low')
            ->assertJsonPath('data.provider', 'test_provider');
    }

    public function test_returns_sanctioned_when_matches_found(): void
    {
        $mock = $this->mock(SanctionsScreeningInterface::class);
        $mock->shouldReceive('screenAddress')
            ->once()
            ->andReturn([
                'matches'       => ['OFAC SDN' => [['name' => 'Test Entity']]],
                'lists_checked' => ['OFAC SDN'],
                'total_matches' => 1,
            ]);
        $mock->shouldReceive('getName')->andReturn('test_provider');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/compliance/check-address?address=0xSanctionedAddress1234567890abcdef12345678');

        $response->assertOk()
            ->assertJsonPath('data.sanctioned', true)
            ->assertJsonPath('data.risk_score', 'high')
            ->assertJsonPath('data.total_matches', 1);
    }

    public function test_uses_ethereum_as_default_network(): void
    {
        $mock = $this->mock(SanctionsScreeningInterface::class);
        $mock->shouldReceive('screenAddress')
            ->with('0x1234567890abcdef1234567890abcdef12345678', 'ethereum')
            ->once()
            ->andReturn([
                'matches'       => [],
                'lists_checked' => [],
                'total_matches' => 0,
            ]);
        $mock->shouldReceive('getName')->andReturn('test_provider');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/compliance/check-address?address=0x1234567890abcdef1234567890abcdef12345678');

        $response->assertOk();
    }

    public function test_accepts_custom_network(): void
    {
        $mock = $this->mock(SanctionsScreeningInterface::class);
        $mock->shouldReceive('screenAddress')
            ->with('0x1234567890abcdef1234567890abcdef12345678', 'polygon')
            ->once()
            ->andReturn([
                'matches'       => [],
                'lists_checked' => [],
                'total_matches' => 0,
            ]);
        $mock->shouldReceive('getName')->andReturn('test_provider');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/compliance/check-address?address=0x1234567890abcdef1234567890abcdef12345678&network=polygon');

        $response->assertOk();
    }

    public function test_returns_503_on_screening_failure(): void
    {
        $mock = $this->mock(SanctionsScreeningInterface::class);
        $mock->shouldReceive('screenAddress')
            ->once()
            ->andThrow(new RuntimeException('Service unavailable'));

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/compliance/check-address?address=0x1234567890abcdef1234567890abcdef12345678');

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SCREENING_UNAVAILABLE');
    }

    public function test_route_exists(): void
    {
        $route = app('router')->getRoutes()->getByName('api.compliance.mobile.check-address');
        $this->assertNotNull($route);
    }
}
