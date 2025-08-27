<?php

namespace Tests\Feature\Http\Middleware;

use App\Domain\Product\Services\SubProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureSubProductEnabledTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var SubProductService&MockInterface
     */
    protected SubProductService|MockInterface $subProductService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock SubProductService
        /** @var SubProductService&MockInterface $mock */
        $mock = Mockery::mock(SubProductService::class);
        $this->subProductService = $mock;
        $this->app->instance(SubProductService::class, $this->subProductService);

        // Set up test routes
        Route::middleware(['sub_product:stablecoins'])->get('/test-stablecoins', function () {
            return response()->json(['message' => 'stablecoins endpoint']);
        });

        Route::middleware(['sub_product:exchange'])->get('/test-exchange', function () {
            return response()->json(['message' => 'exchange endpoint']);
        });

        Route::middleware(['sub_product:lending:p2p'])->get('/test-lending-p2p', function () {
            return response()->json(['message' => 'p2p lending endpoint']);
        });

        Route::middleware(['sub_product:lending:p2p|institutional'])->get('/test-lending-any', function () {
            return response()->json(['message' => 'any lending endpoint']);
        });
    }

    #[Test]
    public function test_allows_access_when_sub_product_is_enabled(): void
    {
        $this->subProductService->shouldReceive('isEnabled')
            ->with('stablecoins')
            ->once()
            ->andReturn(true);

        $response = $this->getJson('/test-stablecoins');

        $response->assertStatus(200)
            ->assertJson(['message' => 'stablecoins endpoint']);
    }

    #[Test]
    public function test_denies_access_when_sub_product_is_disabled(): void
    {
        $this->subProductService->shouldReceive('isEnabled')
            ->with('exchange')
            ->once()
            ->andReturn(false);

        $response = $this->getJson('/test-exchange');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => 'Feature not available',
                'message' => 'The exchange sub-product is not enabled',
            ]);
    }

    #[Test]
    public function test_checks_specific_feature_within_sub_product(): void
    {
        $this->subProductService->shouldReceive('isFeatureEnabled')
            ->with('lending', 'p2p')
            ->once()
            ->andReturn(true);

        $response = $this->getJson('/test-lending-p2p');

        $response->assertStatus(200)
            ->assertJson(['message' => 'p2p lending endpoint']);
    }

    #[Test]
    public function test_denies_access_when_specific_feature_is_disabled(): void
    {
        $this->subProductService->shouldReceive('isFeatureEnabled')
            ->with('lending', 'p2p')
            ->once()
            ->andReturn(false);

        $response = $this->getJson('/test-lending-p2p');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => 'Feature not available',
                'message' => 'The feature p2p is not enabled for sub-product lending',
            ]);
    }

    #[Test]
    public function test_allows_access_when_any_of_multiple_features_is_enabled(): void
    {
        $this->subProductService->shouldReceive('isFeatureEnabled')
            ->with('lending', 'p2p')
            ->once()
            ->andReturn(false);

        $this->subProductService->shouldReceive('isFeatureEnabled')
            ->with('lending', 'institutional')
            ->once()
            ->andReturn(true);

        $response = $this->getJson('/test-lending-any');

        $response->assertStatus(200)
            ->assertJson(['message' => 'any lending endpoint']);
    }

    #[Test]
    public function test_denies_access_when_none_of_multiple_features_is_enabled(): void
    {
        $this->subProductService->shouldReceive('isFeatureEnabled')
            ->with('lending', 'p2p')
            ->once()
            ->andReturn(false);

        $this->subProductService->shouldReceive('isFeatureEnabled')
            ->with('lending', 'institutional')
            ->once()
            ->andReturn(false);

        $response = $this->getJson('/test-lending-any');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => 'Feature not available',
                'message' => 'None of the required features [p2p, institutional] are enabled for sub-product lending',
            ]);
    }

    #[Test]
    public function test_handles_empty_parameter_gracefully(): void
    {
        Route::middleware(['sub_product:'])->get('/test-empty', function () {
            return response()->json(['message' => 'should not reach here']);
        });

        $response = $this->getJson('/test-empty');

        $response->assertStatus(500)
            ->assertJson([
                'error'   => 'Configuration error',
                'message' => 'Sub-product parameter is required',
            ]);
    }

    #[Test]
    public function test_sub_product_not_found_returns_403(): void
    {
        $this->subProductService->shouldReceive('isEnabled')
            ->with('non_existent')
            ->once()
            ->andReturn(false);

        Route::middleware(['sub_product:non_existent'])->get('/test-nonexistent', function () {
            return response()->json(['message' => 'should not reach here']);
        });

        $response = $this->getJson('/test-nonexistent');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => 'Feature not available',
                'message' => 'The non_existent sub-product is not enabled',
            ]);
    }

    #[Test]
    public function test_includes_sub_product_info_in_response_headers(): void
    {
        $this->subProductService->shouldReceive('isEnabled')
            ->with('stablecoins')
            ->once()
            ->andReturn(true);

        $response = $this->getJson('/test-stablecoins');

        $response->assertStatus(200)
            ->assertHeader('X-SubProduct-Required', 'stablecoins');
    }

    #[Test]
    public function test_multiple_sub_product_middleware_can_be_stacked(): void
    {
        Route::middleware(['sub_product:stablecoins', 'sub_product:exchange'])
            ->get('/test-multiple', function () {
                return response()->json(['message' => 'multiple sub-products']);
            });

        $this->subProductService->shouldReceive('isEnabled')
            ->with('stablecoins')
            ->once()
            ->andReturn(true);

        $this->subProductService->shouldReceive('isEnabled')
            ->with('exchange')
            ->once()
            ->andReturn(true);

        $response = $this->getJson('/test-multiple');

        $response->assertStatus(200)
            ->assertJson(['message' => 'multiple sub-products']);
    }

    #[Test]
    public function test_stacked_middleware_fails_if_any_sub_product_disabled(): void
    {
        Route::middleware(['sub_product:stablecoins', 'sub_product:exchange'])
            ->get('/test-multiple-fail', function () {
                return response()->json(['message' => 'should not reach here']);
            });

        $this->subProductService->shouldReceive('isEnabled')
            ->with('stablecoins')
            ->once()
            ->andReturn(true);

        $this->subProductService->shouldReceive('isEnabled')
            ->with('exchange')
            ->once()
            ->andReturn(false);

        $response = $this->getJson('/test-multiple-fail');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => 'Feature not available',
                'message' => 'The exchange sub-product is not enabled',
            ]);
    }
}
