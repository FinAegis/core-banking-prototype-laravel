<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Domain\Product\Services\SubProductService;
use App\Http\Middleware\EnsureSubProductEnabled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

class EnsureSubProductEnabledTest extends UnitTestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected EnsureSubProductEnabled $middleware;

    protected $subProductService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subProductService = \Mockery::mock(SubProductService::class);
        $this->middleware = new EnsureSubProductEnabled($this->subProductService);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_allows_request_when_sub_product_is_enabled()
    {
        $request = Request::create('/api/exchange/orders');

        $this->subProductService
            ->shouldReceive('isEnabled')
            ->once()
            ->with('exchange')
            ->andReturn(true);

        $next = function ($request) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next, 'exchange');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    #[Test]
    public function it_blocks_request_when_sub_product_is_disabled()
    {
        $request = Request::create('/api/lending/loans');

        $this->subProductService
            ->shouldReceive('isEnabled')
            ->once()
            ->with('lending')
            ->andReturn(false);

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next, 'lending');

        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Feature not available', $content['error']);
        $this->assertEquals('The lending sub-product is not enabled', $content['message']);
    }

    #[Test]
    public function it_allows_request_when_feature_is_enabled()
    {
        $request = Request::create('/api/exchange/crypto');

        $this->subProductService
            ->shouldReceive('isFeatureEnabled')
            ->once()
            ->with('exchange', 'crypto_trading')
            ->andReturn(true);

        $next = function ($request) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next, 'exchange:crypto_trading');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    #[Test]
    public function it_blocks_request_when_feature_is_disabled()
    {
        $request = Request::create('/api/exchange/derivatives');

        $this->subProductService
            ->shouldReceive('isFeatureEnabled')
            ->once()
            ->with('exchange', 'derivatives')
            ->andReturn(false);

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next, 'exchange:derivatives');

        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Feature not available', $content['error']);
        $this->assertEquals('The feature derivatives is not enabled for sub-product exchange', $content['message']);
    }

    #[Test]
    public function it_handles_multiple_features_with_or_logic()
    {
        $request = Request::create('/api/lending/loans');

        $this->subProductService
            ->shouldReceive('isFeatureEnabled')
            ->once()
            ->with('lending', 'sme_loans')
            ->andReturn(false);

        $this->subProductService
            ->shouldReceive('isFeatureEnabled')
            ->once()
            ->with('lending', 'p2p_marketplace')
            ->andReturn(true);

        $next = function ($request) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next, 'lending:sme_loans|p2p_marketplace');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    #[Test]
    public function it_blocks_when_all_features_in_or_list_are_disabled()
    {
        $request = Request::create('/api/lending/loans');

        $this->subProductService
            ->shouldReceive('isFeatureEnabled')
            ->once()
            ->with('lending', 'sme_loans')
            ->andReturn(false);

        $this->subProductService
            ->shouldReceive('isFeatureEnabled')
            ->once()
            ->with('lending', 'p2p_marketplace')
            ->andReturn(false);

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next, 'lending:sme_loans|p2p_marketplace');

        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Feature not available', $content['error']);
        $this->assertEquals('None of the required features [sme_loans, p2p_marketplace] are enabled for sub-product lending', $content['message']);
    }

    #[Test]
    public function it_validates_parameter_format()
    {
        $request = Request::create('/api/test');

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        // Test with empty parameter
        $response = $this->middleware->handle($request, $next, '');

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Configuration error', $content['error']);
        $this->assertEquals('Sub-product parameter is required', $content['message']);
    }

    #[Test]
    public function it_handles_ajax_requests()
    {
        $request = Request::create('/api/lending/loans', 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $this->subProductService
            ->shouldReceive('isEnabled')
            ->once()
            ->with('lending')
            ->andReturn(false);

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next, 'lending');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Feature not available', $content['error']);
        $this->assertEquals('The lending sub-product is not enabled', $content['message']);
    }

    #[Test]
    public function it_handles_json_accept_header()
    {
        $request = Request::create('/api/lending/loans', 'GET');
        $request->headers->set('Accept', 'application/json');

        $this->subProductService
            ->shouldReceive('isEnabled')
            ->once()
            ->with('lending')
            ->andReturn(false);

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next, 'lending');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }
}
