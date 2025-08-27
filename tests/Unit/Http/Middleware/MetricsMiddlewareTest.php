<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Domain\Monitoring\Services\MetricsCollector;
use App\Http\Middleware\MetricsMiddleware;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class MetricsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private MetricsMiddleware $middleware;

    private MetricsCollector $metricsCollector;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test to ensure isolation
        Cache::flush();

        $this->metricsCollector = app(MetricsCollector::class);
        $this->middleware = new MetricsMiddleware($this->metricsCollector);
    }

    public function test_middleware_collects_metrics_for_successful_request(): void
    {
        // Arrange
        $request = Request::create('/api/users', 'GET');
        $response = new Response('Success', 200);

        // Act
        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Assert
        $this->assertEquals($response, $result);
        $this->assertEquals(1, Cache::get('metrics:http:requests:total'));
        $this->assertEquals(1, Cache::get('metrics:http:requests:success'));
        $this->assertGreaterThan(0, Cache::get('metrics:http:duration'));
    }

    public function test_middleware_collects_metrics_for_error_response(): void
    {
        // Arrange
        $request = Request::create('/api/users/999', 'GET');
        $response = new Response('Not Found', 404);

        // Act
        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Assert
        $this->assertEquals($response, $result);
        $this->assertEquals(1, Cache::get('metrics:http:requests:total'));
        $this->assertEquals(1, Cache::get('metrics:http:requests:errors'));
    }

    public function test_middleware_measures_request_duration(): void
    {
        // Arrange
        $request = Request::create('/api/slow', 'GET');
        $processingTime = 0.1; // 100ms

        // Act
        $this->middleware->handle($request, function ($req) use ($processingTime) {
            usleep((int) ($processingTime * 1000000)); // Sleep for 100ms

            return new Response('Success', 200);
        });

        // Assert
        $duration = Cache::get('metrics:http:duration');
        $this->assertGreaterThanOrEqual($processingTime, $duration);
    }

    public function test_middleware_tracks_different_http_methods(): void
    {
        // Arrange
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        // Act
        foreach ($methods as $method) {
            $request = Request::create('/api/test', $method);
            $this->middleware->handle($request, function ($req) {
                return new Response('Success', 200);
            });
        }

        // Assert
        $byMethod = Cache::get('metrics:http:by_method', []);
        foreach ($methods as $method) {
            $this->assertEquals(1, $byMethod[$method] ?? 0);
        }
    }

    public function test_middleware_handles_exceptions(): void
    {
        // Arrange
        $request = Request::create('/api/error', 'GET');
        $exception = new Exception('Something went wrong');

        // Act & Assert
        $this->expectException(Exception::class);

        try {
            $this->middleware->handle($request, function ($req) use ($exception) {
                throw $exception;
            });
        } catch (Exception $e) {
            // Verify metrics were still collected
            $this->assertEquals(1, Cache::get('metrics.http.total'));
            $this->assertEquals(1, Cache::get('metrics.http.errors'));
            throw $e;
        }
    }

    public function test_middleware_tracks_route_patterns(): void
    {
        // Arrange
        $routes = [
            'api/users'    => 3,
            'api/posts'    => 2,
            'api/comments' => 1,
        ];

        // Act
        foreach ($routes as $route => $count) {
            for ($i = 0; $i < $count; $i++) {
                $request = Request::create('/' . $route, 'GET');
                $this->middleware->handle($request, function ($req) {
                    return new Response('Success', 200);
                });
            }
        }

        // Assert
        $byPath = Cache::get('metrics:http:by_path', []);
        foreach ($routes as $route => $expectedCount) {
            $this->assertEquals($expectedCount, $byPath[$route] ?? 0);
        }
    }

    public function test_middleware_categorizes_status_codes(): void
    {
        // Clear cache first
        Cache::flush();

        // Arrange
        $statusCodes = [
            200 => '2xx',
            201 => '2xx',
            204 => '2xx',
            301 => '3xx',
            302 => '3xx',
            400 => '4xx',
            404 => '4xx',
            500 => '5xx',
            503 => '5xx',
        ];

        $errorCount = 0;
        $serverErrorCount = 0;
        $clientErrorCount = 0;

        // Act
        foreach ($statusCodes as $code => $category) {
            $request = Request::create('/api/test', 'GET');
            $this->middleware->handle($request, function ($req) use ($code) {
                return new Response('Response', $code);
            });

            if ($code >= 400) {
                $errorCount++;
                if ($code >= 500) {
                    $serverErrorCount++;
                } else {
                    $clientErrorCount++;
                }
            }
        }

        // Assert - check what the middleware actually tracks
        $this->assertEquals(9, Cache::get('metrics:requests:total')); // Total requests
        $this->assertEquals(4, Cache::get('metrics:errors:total')); // 400, 404, 500, 503
        $this->assertEquals(2, Cache::get('metrics:errors:server')); // 500, 503
        $this->assertEquals(2, Cache::get('metrics:errors:client')); // 400, 404
    }

    public function test_middleware_does_not_interfere_with_response(): void
    {
        // Arrange
        $request = Request::create('/api/data', 'GET');
        $responseData = ['id' => 1, 'name' => 'Test'];
        $response = response()->json($responseData, 200);

        // Act
        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Assert
        $this->assertEquals($response->getContent(), $result->getContent());
        $this->assertEquals($response->getStatusCode(), $result->getStatusCode());
        $this->assertEquals($responseData, json_decode($result->getContent(), true));
    }

    public function test_middleware_handles_json_responses(): void
    {
        // Clear cache first
        Cache::flush();

        // Arrange
        $request = Request::create('/api/json', 'POST');
        $request->headers->set('Content-Type', 'application/json');
        $response = response()->json(['success' => true], 201);

        // Act
        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Assert
        $this->assertEquals(201, $result->getStatusCode());
        $this->assertGreaterThanOrEqual(1, Cache::get('metrics:requests:total'));
        $this->assertGreaterThanOrEqual(1, Cache::get('metrics:http:requests:success'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
