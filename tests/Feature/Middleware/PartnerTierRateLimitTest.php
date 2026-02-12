<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Domain\FinancialInstitution\Enums\PartnerTier;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Services\PartnerUsageMeteringService;
use App\Http\Middleware\ApiRateLimitMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class PartnerTierRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('rate_limiting.enabled', true);
        Config::set('rate_limiting.force_in_tests', true);
        Config::set('rate_limiting.partner_tiers.enabled', true);
        Config::set('rate_limiting.partner_tiers.enforce_monthly_limits', true);
        Config::set('rate_limiting.partner_tiers.type_multipliers', [
            'query'       => 1.0,
            'transaction' => 0.5,
            'auth'        => 0.1,
            'webhook'     => 2.0,
            'admin'       => 1.0,
            'public'      => 1.0,
        ]);
    }

    public function test_partner_gets_tier_based_rate_limit(): void
    {
        $partner = $this->createMockPartner(PartnerTier::GROWTH);

        $request = Request::create('/api/v1/accounts', 'GET');
        $request->attributes->set('partner', $partner);

        $middleware = new ApiRateLimitMiddleware();
        $response = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]), 'query');

        $this->assertEquals(200, $response->getStatusCode());
        // Growth tier = 300 req/min * 1.0 multiplier = 300
        $this->assertEquals(300, $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_partner_transaction_type_gets_multiplied_limit(): void
    {
        $partner = $this->createMockPartner(PartnerTier::ENTERPRISE);

        $request = Request::create('/api/v1/transfer', 'POST');
        $request->attributes->set('partner', $partner);

        $middleware = new ApiRateLimitMiddleware();
        $response = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]), 'transaction');

        $this->assertEquals(200, $response->getStatusCode());
        // Enterprise tier = 1000 req/min * 0.5 multiplier = 500
        $this->assertEquals(500, $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_non_partner_request_uses_default_limits(): void
    {
        $request = Request::create('/api/v1/accounts', 'GET');

        $middleware = new ApiRateLimitMiddleware();
        $response = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]), 'query');

        $this->assertEquals(200, $response->getStatusCode());
        // Default query limit = 100
        $this->assertEquals(100, $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_partner_monthly_limit_exceeded_returns_429(): void
    {
        $partner = $this->createMockPartner(PartnerTier::STARTER);

        $meteringService = Mockery::mock(PartnerUsageMeteringService::class);
        $meteringService->shouldReceive('checkUsageLimit')
            ->with($partner)
            ->andReturn([
                'exceeded'   => true,
                'current'    => 10001,
                'limit'      => 10000,
                'percentage' => 100.01,
            ]);
        $this->app->instance(PartnerUsageMeteringService::class, $meteringService);

        $request = Request::create('/api/v1/accounts', 'GET');
        $request->attributes->set('partner', $partner);

        $middleware = new ApiRateLimitMiddleware();
        $response = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]), 'query');

        $this->assertEquals(429, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('MONTHLY_LIMIT_EXCEEDED', $body['error']['code']);
        $this->assertEquals(10000, $body['error']['limit']);
        $this->assertEquals(10001, $body['error']['used']);
        $this->assertNotEmpty($response->headers->get('X-Monthly-Limit'));
    }

    public function test_partner_tier_rate_limiting_disabled_falls_through(): void
    {
        Config::set('rate_limiting.partner_tiers.enabled', false);

        $partner = $this->createMockPartner(PartnerTier::ENTERPRISE);

        $request = Request::create('/api/v1/accounts', 'GET');
        $request->attributes->set('partner', $partner);

        $middleware = new ApiRateLimitMiddleware();
        $response = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]), 'query');

        $this->assertEquals(200, $response->getStatusCode());
        // Falls through to default query limit = 100
        $this->assertEquals(100, $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_starter_tier_auth_limit_is_applied(): void
    {
        $partner = $this->createMockPartner(PartnerTier::STARTER);

        $request = Request::create('/api/v1/login', 'POST');
        $request->attributes->set('partner', $partner);

        $middleware = new ApiRateLimitMiddleware();
        $response = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]), 'auth');

        $this->assertEquals(200, $response->getStatusCode());
        // Starter tier = 60 req/min * 0.1 multiplier = 6
        $this->assertEquals(6, $response->headers->get('X-RateLimit-Limit'));
    }

    private function createMockPartner(PartnerTier $tier): FinancialInstitutionPartner
    {
        $partner = Mockery::mock(FinancialInstitutionPartner::class)->makePartial();
        $partner->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $partner->shouldReceive('setAttribute')->andReturnNull();
        $partner->shouldReceive('getTierEnum')->andReturn($tier);

        // Mock metering service for non-exceeded checks
        $meteringService = Mockery::mock(PartnerUsageMeteringService::class);
        $meteringService->shouldReceive('checkUsageLimit')->andReturn([
            'exceeded'   => false,
            'current'    => 100,
            'limit'      => $tier->apiCallLimit(),
            'percentage' => 1.0,
        ]);
        $meteringService->shouldReceive('recordApiCall')->andReturnNull();
        $this->app->instance(PartnerUsageMeteringService::class, $meteringService);

        return $partner;
    }
}
