<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RegTech\Models;

use App\Domain\RegTech\Models\RegulatoryEndpoint;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegulatoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-02-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_can_create_regulatory_endpoint(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'FinCEN BSA E-Filing',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://bsaefiling.fincen.treas.gov/api',
        ]);

        $this->assertDatabaseHas('regulatory_endpoints', [
            'name'         => 'FinCEN BSA E-Filing',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
        ]);

        $this->assertNotNull($endpoint->uuid);
    }

    public function test_generates_uuid_on_creation(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Test Endpoint',
            'regulator'    => 'FCA',
            'jurisdiction' => 'UK',
            'base_url'     => 'https://api.fca.org.uk',
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $endpoint->uuid
        );
    }

    public function test_active_scope(): void
    {
        RegulatoryEndpoint::create([
            'name'         => 'Active Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
            'is_active'    => true,
        ]);

        RegulatoryEndpoint::create([
            'name'         => 'Inactive Endpoint',
            'regulator'    => 'ESMA',
            'jurisdiction' => 'EU',
            'base_url'     => 'https://api.esma.eu',
            'is_active'    => false,
        ]);

        $activeEndpoints = RegulatoryEndpoint::active()->get();

        $this->assertCount(1, $activeEndpoints);
        $this->assertEquals('Active Endpoint', $activeEndpoints->first()->name);
    }

    public function test_jurisdiction_scope(): void
    {
        RegulatoryEndpoint::create([
            'name'         => 'US Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.fincen.gov',
        ]);

        RegulatoryEndpoint::create([
            'name'         => 'EU Endpoint',
            'regulator'    => 'ESMA',
            'jurisdiction' => 'EU',
            'base_url'     => 'https://api.esma.eu',
        ]);

        $usEndpoints = RegulatoryEndpoint::jurisdiction('US')->get();

        $this->assertCount(1, $usEndpoints);
        $this->assertEquals('US Endpoint', $usEndpoints->first()->name);
    }

    public function test_sandbox_scope(): void
    {
        RegulatoryEndpoint::create([
            'name'         => 'Sandbox Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://sandbox.api.example.com',
            'is_sandbox'   => true,
        ]);

        RegulatoryEndpoint::create([
            'name'         => 'Production Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
            'is_sandbox'   => false,
        ]);

        $sandboxEndpoints = RegulatoryEndpoint::sandbox()->get();
        $productionEndpoints = RegulatoryEndpoint::production()->get();

        $this->assertCount(1, $sandboxEndpoints);
        $this->assertCount(1, $productionEndpoints);
    }

    public function test_build_url(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Test Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
            'api_version'  => 'v2',
        ]);

        $this->assertEquals(
            'https://api.example.com/v2/reports/submit',
            $endpoint->buildUrl('/reports/submit')
        );
    }

    public function test_build_url_without_version(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Test Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
        ]);

        $this->assertEquals(
            'https://api.example.com/health',
            $endpoint->buildUrl('/health')
        );
    }

    public function test_api_credentials_encryption(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'                 => 'Test Endpoint',
            'regulator'            => 'FinCEN',
            'jurisdiction'         => 'US',
            'base_url'             => 'https://api.example.com',
            'api_key_encrypted'    => 'test-api-key-123',
            'api_secret_encrypted' => 'test-secret-456',
        ]);

        // Fetch fresh from database
        $freshEndpoint = RegulatoryEndpoint::find($endpoint->id);

        // Values should be decrypted when accessed
        $this->assertEquals('test-api-key-123', $freshEndpoint->api_key_encrypted);
        $this->assertEquals('test-secret-456', $freshEndpoint->api_secret_encrypted);
    }

    public function test_update_health_status(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Test Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
        ]);

        $endpoint->updateHealthStatus(RegulatoryEndpoint::HEALTH_HEALTHY);

        $this->assertEquals(RegulatoryEndpoint::HEALTH_HEALTHY, $endpoint->health_status);
        $this->assertNotNull($endpoint->last_health_check);
    }

    public function test_update_health_status_with_error(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Test Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
        ]);

        $endpoint->updateHealthStatus(RegulatoryEndpoint::HEALTH_UNHEALTHY, 'Connection timeout');

        // Refresh from database
        $endpoint->refresh();

        $this->assertEquals(RegulatoryEndpoint::HEALTH_UNHEALTHY, $endpoint->health_status);
        $this->assertEquals('Connection timeout', $endpoint->metadata['last_health_message'] ?? null);
    }

    public function test_health_status_constants(): void
    {
        $this->assertEquals('healthy', RegulatoryEndpoint::HEALTH_HEALTHY);
        $this->assertEquals('degraded', RegulatoryEndpoint::HEALTH_DEGRADED);
        $this->assertEquals('unhealthy', RegulatoryEndpoint::HEALTH_UNHEALTHY);
        $this->assertEquals('unknown', RegulatoryEndpoint::HEALTH_UNKNOWN);
    }

    public function test_headers_cast_to_array(): void
    {
        $headers = ['Authorization' => 'Bearer token', 'X-Custom-Header' => 'value'];

        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Test Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
            'headers'      => $headers,
        ]);

        $this->assertIsArray($endpoint->headers);
        $this->assertEquals($headers, $endpoint->headers);
    }

    public function test_auth_config_cast_to_array(): void
    {
        $authConfig = ['type' => 'oauth2', 'token_url' => 'https://auth.example.com/token'];

        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Test Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
            'auth_config'  => $authConfig,
        ]);

        $this->assertIsArray($endpoint->auth_config);
        $this->assertEquals($authConfig, $endpoint->auth_config);
    }

    public function test_default_values(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'Minimal Endpoint',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
        ]);

        $this->assertEquals('filing', $endpoint->endpoint_type);
        $this->assertTrue($endpoint->is_sandbox);
        $this->assertTrue($endpoint->is_active);
        $this->assertEquals(60, $endpoint->rate_limit_per_minute);
        $this->assertEquals(30, $endpoint->timeout_seconds);
        $this->assertEquals('unknown', $endpoint->health_status);
    }

    public function test_soft_delete(): void
    {
        $endpoint = RegulatoryEndpoint::create([
            'name'         => 'To Be Deleted',
            'regulator'    => 'FinCEN',
            'jurisdiction' => 'US',
            'base_url'     => 'https://api.example.com',
        ]);

        $endpoint->delete();

        $this->assertSoftDeleted('regulatory_endpoints', ['id' => $endpoint->id]);
        $this->assertCount(0, RegulatoryEndpoint::all());
        $this->assertCount(1, RegulatoryEndpoint::withTrashed()->get());
    }
}
