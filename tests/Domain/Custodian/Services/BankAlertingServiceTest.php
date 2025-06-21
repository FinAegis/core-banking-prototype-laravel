<?php

declare(strict_types=1);

use App\Domain\Custodian\Events\CustodianHealthChanged;
use App\Domain\Custodian\Services\BankAlertingService;
use App\Domain\Custodian\Services\CustodianHealthMonitor;
use App\Models\User;
use App\Notifications\BankHealthAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->healthMonitor = Mockery::mock(CustodianHealthMonitor::class);
    $this->alertingService = new BankAlertingService($this->healthMonitor);
    Cache::flush(); // Clear cache before each test
});

it('sends critical alert when bank becomes unhealthy', function () {
    Notification::fake();
    
    // Create admin user
    $admin = User::factory()->create();
    
    // Ensure user exists
    expect(User::where('id', $admin->id)->exists())->toBeTrue();
    
    // Create health change event
    $event = new CustodianHealthChanged(
        custodian: 'paysera',
        previousStatus: 'healthy',
        newStatus: 'unhealthy',
        timestamp: now()
    );
    
    // Mock health monitor response
    $this->healthMonitor->shouldReceive('getCustodianHealth')
        ->with('paysera')
        ->andReturn([
            'custodian' => 'paysera',
            'status' => 'unhealthy',
            'overall_failure_rate' => 75.5,
            'recommendations' => ['Consider switching to alternative custodian'],
        ]);
    
    // Handle the event
    $this->alertingService->handleHealthChange($event);
    
    // Assert notification was sent
    Notification::assertSentTo(
        [$admin],
        BankHealthAlert::class
    );
});

it('sends warning alert when bank becomes degraded', function () {
    Notification::fake();
    
    $admin = User::factory()->create();
    
    $event = new CustodianHealthChanged(
        custodian: 'deutsche_bank',
        previousStatus: 'healthy',
        newStatus: 'degraded',
        timestamp: now()
    );
    
    $this->healthMonitor->shouldReceive('getCustodianHealth')
        ->with('deutsche_bank')
        ->andReturn([
            'custodian' => 'deutsche_bank',
            'status' => 'degraded',
            'overall_failure_rate' => 35.0,
            'recommendations' => ['Monitor closely for further degradation'],
        ]);
    
    $this->alertingService->handleHealthChange($event);
    
    Notification::assertSentTo(
        [$admin],
        BankHealthAlert::class,
        function ($notification) {
            return $notification->severity === 'warning';
        }
    );
});

it('does not send alert for recovery events', function () {
    Notification::fake();
    
    $event = new CustodianHealthChanged(
        custodian: 'santander',
        previousStatus: 'unhealthy',
        newStatus: 'healthy',
        timestamp: now()
    );
    
    $this->alertingService->handleHealthChange($event);
    
    Notification::assertNothingSent();
});

it('respects alert cooldown period', function () {
    Notification::fake();
    
    $admin = User::factory()->create();
    
    $event = new CustodianHealthChanged(
        custodian: 'paysera',
        previousStatus: 'healthy',
        newStatus: 'unhealthy',
        timestamp: now()
    );
    
    $this->healthMonitor->shouldReceive('getCustodianHealth')
        ->with('paysera')
        ->andReturn([
            'custodian' => 'paysera',
            'status' => 'unhealthy',
            'overall_failure_rate' => 80.0,
        ]);
    
    // First alert should be sent
    $this->alertingService->handleHealthChange($event);
    
    Notification::assertSentTo($admin, BankHealthAlert::class);
    // Don't check total count as other users may exist from other tests
    
    // Second alert within cooldown should not be sent
    $this->alertingService->handleHealthChange($event);
    
    // No new notifications should be sent (cooldown active)
    Notification::assertSentTo($admin, BankHealthAlert::class, 1);
});

it('performs system-wide health check and alerts on multiple failures', function () {
    $this->healthMonitor->shouldReceive('getAllCustodiansHealth')
        ->andReturn([
            'paysera' => [
                'status' => 'unhealthy',
                'overall_failure_rate' => 85.0,
            ],
            'deutsche_bank' => [
                'status' => 'degraded',
                'overall_failure_rate' => 45.0,
            ],
            'santander' => [
                'status' => 'healthy',
                'overall_failure_rate' => 5.0,
            ],
        ]);
    
    // Should log critical alert
    Log::shouldReceive('alert')
        ->once()
        ->with(
            'System-wide bank health alert',
            Mockery::on(function ($context) {
                return $context['severity'] === 'critical' &&
                       str_contains($context['message'], 'Multiple bank connectors');
            })
        );
    
    $this->alertingService->performHealthCheck();
});