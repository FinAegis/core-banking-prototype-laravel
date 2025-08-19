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
    Cache::flush();
});

describe('health endpoint', function () {
    it('returns health status without authentication', function () {
        // Mock healthy services
        DB::shouldReceive('connection->getPdo')->andReturn(true);
        Redis::shouldReceive('ping')->andReturn('PONG');
        Queue::shouldReceive('size')->andReturn(100);
        
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
                'status' => 'healthy',
            ]);
    });
    
    it('returns degraded status when a service is unhealthy', function () {
        // Mock database failure
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new Exception('Database connection failed'));
        Redis::shouldReceive('ping')->andReturn('PONG');
        Queue::shouldReceive('size')->andReturn(100);
        
        $response = $this->getJson('/api/monitoring/health');
        
        $response->assertOk()
            ->assertJson([
                'status' => 'degraded',
            ]);
    });
    
    it('returns unhealthy status when multiple services fail', function () {
        // Mock multiple failures
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new Exception('Database connection failed'));
        Redis::shouldReceive('ping')
            ->once()
            ->andThrow(new Exception('Redis connection failed'));
        Queue::shouldReceive('size')->andReturn(100);
        
        $response = $this->getJson('/api/monitoring/health');
        
        $response->assertServiceUnavailable()
            ->assertJson([
                'status' => 'unhealthy',
            ]);
    });
});

describe('prometheus endpoint', function () {
    it('returns metrics in Prometheus format without authentication', function () {
        // Set up some test metrics
        Cache::put('monitoring:metrics', [
            'http_requests_total:method=GET' => [
                'type' => 'counter',
                'value' => 1234,
                'description' => 'Total HTTP requests',
            ],
        ]);
        
        $response = $this->get('/api/monitoring/prometheus');
        
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4')
            ->assertSee('# HELP http_requests_total Total HTTP requests')
            ->assertSee('# TYPE http_requests_total counter')
            ->assertSee('http_requests_total{method="GET"} 1234');
    });
});

describe('metrics endpoint', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/monitoring/metrics');
        
        $response->assertUnauthorized();
    });
    
    it('returns metrics in JSON format', function () {
        Sanctum::actingAs($this->user);
        
        Cache::put('monitoring:metrics', [
            'test_metric:env=prod' => [
                'type' => 'gauge',
                'value' => 42.5,
                'description' => 'Test metric',
            ],
        ]);
        
        $response = $this->getJson('/api/monitoring/metrics');
        
        $response->assertOk()
            ->assertJsonStructure([
                'metrics',
                'timestamp',
            ])
            ->assertJsonCount(1, 'metrics');
    });
});

describe('traces endpoint', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/monitoring/traces');
        
        $response->assertUnauthorized();
    });
    
    it('returns trace data', function () {
        Sanctum::actingAs($this->user);
        
        // Set up test trace data
        Cache::put('tracing:traces', [
            'trace-123' => [
                'trace_id' => 'trace-123',
                'operation_name' => 'test-operation',
                'start_time' => now()->timestamp,
                'spans' => [
                    'span-456' => [
                        'span_id' => 'span-456',
                        'operation_name' => 'test-span',
                        'duration' => 100.5,
                    ],
                ],
            ],
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
            [
                'name' => 'high_cpu_usage',
                'severity' => 'critical',
                'message' => 'CPU usage above 90%',
                'context' => json_encode(['cpu' => 92]),
                'acknowledged' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'low_memory',
                'severity' => 'warning',
                'message' => 'Memory usage above 80%',
                'context' => json_encode(['memory' => 85]),
                'acknowledged' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
            [
                'name' => 'critical_alert',
                'severity' => 'critical',
                'message' => 'Critical issue',
                'acknowledged' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'warning_alert',
                'severity' => 'warning',
                'message' => 'Warning issue',
                'acknowledged' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
            [
                'name' => 'active_alert',
                'severity' => 'error',
                'message' => 'Active issue',
                'acknowledged' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'acknowledged_alert',
                'severity' => 'error',
                'message' => 'Acknowledged issue',
                'acknowledged' => true,
                'acknowledged_by' => $this->user->id,
                'acknowledged_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        
        $response = $this->getJson('/api/monitoring/alerts');
        
        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('alerts.0.name', 'active_alert');
    });
});

describe('acknowledge alert endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/monitoring/alerts/1/acknowledge');
        
        $response->assertUnauthorized();
    });
    
    it('acknowledges an alert', function () {
        Sanctum::actingAs($this->user);
        
        // Create an alert
        $alertId = DB::table('monitoring_alerts')->insertGetId([
            'name' => 'test_alert',
            'severity' => 'warning',
            'message' => 'Test alert',
            'acknowledged' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $response = $this->postJson("/api/monitoring/alerts/{$alertId}/acknowledge");
        
        $response->assertOk()
            ->assertJson([
                'message' => 'Alert acknowledged successfully',
                'alert' => [
                    'id' => $alertId,
                    'acknowledged' => true,
                    'acknowledged_by' => $this->user->id,
                ],
            ]);
        
        // Verify in database
        $alert = DB::table('monitoring_alerts')->find($alertId);
        expect($alert->acknowledged)->toBe(1);
        expect($alert->acknowledged_by)->toBe($this->user->id);
        expect($alert->acknowledged_at)->not->toBeNull();
    });
    
    it('returns 404 for non-existent alert', function () {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/monitoring/alerts/999/acknowledge');
        
        $response->assertNotFound();
    });
});

describe('start monitoring session endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/monitoring/sessions/start');
        
        $response->assertUnauthorized();
    });
    
    it('starts a new monitoring session', function () {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/monitoring/sessions/start', [
            'metadata' => [
                'type' => 'performance_monitoring',
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
            'name' => 'api_requests',
            'value' => 42.5,
            'type' => 'counter',
            'labels' => [
                'endpoint' => '/api/users',
                'method' => 'GET',
            ],
            'unit' => 'requests',
        ]);
        
        $response->assertCreated()
            ->assertJson([
                'message' => 'Metric recorded successfully',
                'metric' => [
                    'name' => 'api_requests',
                    'value' => 42.5,
                    'type' => 'counter',
                ],
            ]);
        
        // Verify metric was stored
        $metrics = Cache::get('monitoring:metrics', []);
        expect($metrics)->toHaveKey('api_requests:endpoint=/api/users,method=GET');
    });
    
    it('validates metric type', function () {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/monitoring/metrics/record', [
            'name' => 'test_metric',
            'value' => 10,
            'type' => 'invalid_type',
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