<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    // Clear only monitoring-related cache keys to avoid conflicts in parallel tests
    Cache::forget('metrics:http:requests:total');
    Cache::forget('metrics:http:requests:status:200');
    Cache::forget('metrics:http:methods:GET');
    Cache::forget('metrics:cache:hits');
    Cache::forget('monitoring:traces:keys');
});

afterEach(function () {
    // Clear any facade mocks
    Mockery::close();
});

describe('health endpoint', function () {
    it('returns health status without authentication', function () {
        // The health check will use real database connections in test environment
        // No mocking needed as RefreshDatabase trait ensures clean state

        $response = $this->getJson('/api/monitoring/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database',
                    'redis',
                    'queue',
                ],
                'timestamp',
            ])
            ->assertJson([
                'status'  => 'healthy',
                'healthy' => true,
            ]);
    });

    it('returns unhealthy status when a service is unhealthy', function () {
        // Mock database failure
        DB::shouldReceive('select')->with('SELECT 1')->andThrow(new Exception('Database connection failed'));
        DB::shouldReceive('table')->with('failed_jobs')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('table')->with('jobs')->andReturnSelf();
        Redis::shouldReceive('ping')->andReturn('PONG');
        Queue::shouldReceive('size')->andReturn(100);

        $response = $this->getJson('/api/monitoring/health');

        $response->assertStatus(503)
            ->assertJson([
                'status'  => 'unhealthy',
                'healthy' => false,
            ]);
    });

    it('returns unhealthy status when multiple services fail', function () {
        // Mock multiple failures
        DB::shouldReceive('select')->with('SELECT 1')->andThrow(new Exception('Database connection failed'));
        DB::shouldReceive('table')->andThrow(new Exception('Database unavailable'));
        Redis::shouldReceive('ping')->andThrow(new Exception('Redis connection failed'));
        Queue::shouldReceive('size')->andReturn(100);

        $response = $this->getJson('/api/monitoring/health');

        $response->assertServiceUnavailable()
            ->assertJson([
                'status'  => 'unhealthy',
                'healthy' => false,
            ]);
    });
});

describe('prometheus endpoint', function () {
    it('returns metrics in Prometheus format without authentication', function () {
        // Set up some test metrics matching what PrometheusExporter expects
        Cache::put('metrics:http:requests:total', 1234);
        Cache::put('metrics:http:requests:status:200', 1000);
        Cache::put('metrics:cache:hits', 500);

        $response = $this->get('/api/monitoring/prometheus');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8')
            ->assertSee('# HELP http_requests_total Total HTTP requests')
            ->assertSee('# TYPE http_requests_total counter')
            ->assertSee('http_requests_total 1234');
    });
});

describe('metrics endpoint', function () {
    it('returns Prometheus metrics without authentication', function () {
        // Set some cache metrics
        Cache::put('metrics:http:requests:total', 100);
        Cache::put('metrics:http:methods:GET', 50);

        $response = $this->get('/api/monitoring/metrics');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $response->assertSee('http_requests_total');
    });

    it('returns JSON metrics with authentication', function () {
        Sanctum::actingAs($this->user);

        // Set some cache metrics
        Cache::put('metrics:http:requests:total', 100);
        Cache::put('metrics:cache:hits', 50);

        $response = $this->getJson('/api/monitoring/metrics-json');

        $response->assertOk()
            ->assertJsonStructure([
                'metrics',
                'timestamp',
                'count',
            ])
            ->assertJsonPath('metrics.http_requests_total', '100')
            ->assertJsonPath('metrics.cache_hits', '50');
    });
});

describe('traces endpoint', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/monitoring/traces');

        $response->assertUnauthorized();
    });

    it('returns trace data', function () {
        Sanctum::actingAs($this->user);

        // Set up test trace data using the correct cache keys
        $traceId = 'trace-123';
        Cache::put('monitoring:traces:keys', [$traceId]);
        Cache::put("trace:{$traceId}", [
            'trace_id'       => $traceId,
            'operation_name' => 'test-operation',
            'start_time'     => now()->timestamp,
        ]);

        $response = $this->getJson('/api/monitoring/traces');

        $response->assertOk()
            ->assertJsonStructure([
                'traces',
                'count',
                'timestamp',
            ])
            ->assertJsonPath('count', 1);
    });
});

describe('alerts endpoint', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/monitoring/alerts');

        $response->assertUnauthorized();
    });

    it('returns active alerts', function () {
        Sanctum::actingAs($this->user);

        // Create test alerts
        DB::table('monitoring_alerts')->insert([
            'name'         => 'high_cpu_usage',
            'severity'     => 'critical',
            'message'      => 'CPU usage above 90%',
            'context'      => json_encode(['cpu' => 92]),
            'acknowledged' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('monitoring_alerts')->insert([
            'name'         => 'low_memory',
            'severity'     => 'warning',
            'message'      => 'Memory usage above 80%',
            'context'      => json_encode(['memory' => 85]),
            'acknowledged' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->getJson('/api/monitoring/alerts');

        $response->assertOk()
            ->assertJsonStructure([
                'alerts' => [
                    '*' => [
                        'id',
                        'name',
                        'severity',
                        'message',
                        'context',
                        'acknowledged',
                        'created_at',
                    ],
                ],
                'count',
                'timestamp',
            ])
            ->assertJsonPath('count', 2);
    });

    it('filters alerts by severity', function () {
        Sanctum::actingAs($this->user);

        // Create alerts with different severities
        DB::table('monitoring_alerts')->insert([
            'name'         => 'critical_alert',
            'severity'     => 'critical',
            'message'      => 'Critical issue',
            'context'      => null,
            'acknowledged' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('monitoring_alerts')->insert([
            'name'         => 'warning_alert',
            'severity'     => 'warning',
            'message'      => 'Warning issue',
            'context'      => null,
            'acknowledged' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->getJson('/api/monitoring/alerts?severity=critical');

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('alerts.0.severity', 'critical');
    });

    it('excludes acknowledged alerts by default', function () {
        Sanctum::actingAs($this->user);

        // Create acknowledged and unacknowledged alerts
        DB::table('monitoring_alerts')->insert([
            'name'         => 'active_alert',
            'severity'     => 'error',
            'message'      => 'Active issue',
            'context'      => null,
            'acknowledged' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('monitoring_alerts')->insert([
            'name'            => 'acknowledged_alert',
            'severity'        => 'error',
            'message'         => 'Acknowledged issue',
            'context'         => null,
            'acknowledged'    => true,
            'acknowledged_by' => $this->user->id,
            'acknowledged_at' => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->getJson('/api/monitoring/alerts');

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('alerts.0.name', 'active_alert');
    });
});

describe('acknowledge alert endpoint', function () {
    it('requires authentication', function () {
        $response = $this->putJson('/api/monitoring/alerts/1/acknowledge');

        $response->assertUnauthorized();
    });

    it('acknowledges an alert', function () {
        Sanctum::actingAs($this->user);

        // Create an alert
        $alertId = DB::table('monitoring_alerts')->insertGetId([
            'name'         => 'test_alert',
            'severity'     => 'warning',
            'message'      => 'Test alert',
            'acknowledged' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->putJson("/api/monitoring/alerts/{$alertId}/acknowledge");

        $response->assertOk()
            ->assertJson([
                'message'  => 'Alert acknowledged',
                'alert_id' => $alertId,
            ]);

        // Verify in database
        $alert = DB::table('monitoring_alerts')->where('id', $alertId)->first();
        expect($alert)->not->toBeNull();
        expect($alert)->toBeObject();
        expect($alert->acknowledged)->toBe(1);
        expect($alert->acknowledged_by)->toBe($this->user->id);
        expect($alert->acknowledged_at)->not->toBeNull();
    });

    it('returns 404 for non-existent alert', function () {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/monitoring/alerts/999/acknowledge');

        $response->assertNotFound();
    });
});

// These tests are for endpoints that don't exist yet - commented out for now
/*
describe('start monitoring session endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/monitoring/sessions/start');

        $response->assertUnauthorized();
    });

    it('starts a new monitoring session', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/monitoring/sessions/start', [
            'metadata' => [
                'type'      => 'performance_monitoring',
                'component' => 'api',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'session_id',
                'started_at',
                'metadata',
            ]);

        $sessionId = $response->json('session_id');
        expect($sessionId)->toBeString();
        expect($sessionId)->not->toBeEmpty();
    });
});

describe('record metric endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/monitoring/metrics/record');

        $response->assertUnauthorized();
    });

    it('records a metric', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/monitoring/metrics/record', [
            'name'   => 'api_requests',
            'value'  => 42.5,
            'type'   => 'counter',
            'labels' => [
                'endpoint' => '/api/users',
                'method'   => 'GET',
            ],
            'unit' => 'requests',
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Metric recorded successfully',
                'metric'  => [
                    'name'  => 'api_requests',
                    'value' => 42.5,
                    'type'  => 'counter',
                ],
            ]);

        // Verify metric was stored
        $metrics = Cache::get('monitoring:metrics', []);
        expect($metrics)->toHaveKey('api_requests:endpoint=/api/users,method=GET');
    });

    it('validates metric type', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/monitoring/metrics/record', [
            'name'  => 'test_metric',
            'value' => 10,
            'type'  => 'invalid_type',
        ]);

        $response->assertUnprocessableEntity()
            ->assertJsonValidationErrors(['type']);
    });
});

describe('performance snapshot endpoint', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/monitoring/performance');

        $response->assertUnauthorized();
    });

    it('returns performance snapshot', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/monitoring/performance');

        $response->assertOk()
            ->assertJsonStructure([
                'memory' => [
                    'current',
                    'peak',
                    'limit',
                ],
                'cpu' => [
                    'load_average',
                ],
                'database' => [
                    'connections',
                    'slow_queries',
                ],
                'cache' => [
                    'hits',
                    'misses',
                    'hit_rate',
                ],
                'timestamp',
            ]);
    });
});
*/
