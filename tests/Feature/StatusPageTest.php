<?php

declare(strict_types=1);

use App\Models\SystemHealthCheck;

/*
 * The status page shows a status only for services that something really
 * checks. Services without a check are shown as not monitored, never as
 * operational, and no uptime figure is made up for them.
 */

/**
 * @param  array<int, array<string, mixed>>  $services
 * @return array<string, mixed>
 */
function statusPageService(array $services, string $name): array
{
    $service = collect($services)->firstWhere('name', $name);
    if (! is_array($service)) {
        throw new RuntimeException("The status page does not list {$name}.");
    }

    return $service;
}

describe('Status page', function (): void {
    it('shows unchecked services as not monitored instead of operational', function (): void {
        $response = $this->get('/status');
        $response->assertOk();

        $services = $response->viewData('services');
        foreach (['Sign-in', 'Query interface', 'Pay-per-request payments', 'Email', 'Blockchain networks'] as $name) {
            $service = statusPageService($services, $name);
            expect($service['status'])->toBe('not_monitored')
                ->and($service['uptime'])->toBeNull();
        }

        $response->assertSee('Not monitored');
        $response->assertDontSee('All Systems Operational');
    });

    it('shows the live checks of the database, cache, background tasks and file storage', function (): void {
        $response = $this->get('/status');
        $response->assertOk();

        $services = $response->viewData('services');
        foreach (['Database', 'Cache', 'Background tasks', 'File storage'] as $name) {
            expect(statusPageService($services, $name)['status'])->toBeIn(['operational', 'degraded', 'down']);
        }
    });

    it('ignores the email check because it does not test sending', function (): void {
        SystemHealthCheck::create([
            'service'    => 'email',
            'check_type' => 'scheduled',
            'status'     => 'operational',
            'checked_at' => now(),
        ]);

        $response = $this->getJson('/api/status');
        $response->assertOk();

        $email = statusPageService($response->json('services'), 'Email');
        expect($email['status'])->toBe('not_monitored')
            ->and($email['uptime'])->toBeNull()
            ->and($response->json('status.checks'))->not->toHaveKey('email');
    });

    it('shows the website from a recent scheduled check and no status without one', function (): void {
        $response = $this->getJson('/api/status');
        expect(statusPageService($response->json('services'), 'Website')['status'])->toBe('unknown');

        SystemHealthCheck::create([
            'service'    => 'web',
            'check_type' => 'scheduled',
            'status'     => 'operational',
            'checked_at' => now()->subHour(),
        ]);
        $response = $this->getJson('/api/status');
        expect(statusPageService($response->json('services'), 'Website')['status'])->toBe('unknown');

        SystemHealthCheck::create([
            'service'    => 'web',
            'check_type' => 'scheduled',
            'status'     => 'operational',
            'checked_at' => now()->subMinutes(3),
        ]);
        $response = $this->getJson('/api/status');
        $website = statusPageService($response->json('services'), 'Website');
        expect($website['status'])->toBe('operational')
            ->and($website['uptime'])->toBe('100%');
    });

    it('does not report an uptime figure without checks', function (): void {
        expect(SystemHealthCheck::calculateUptime('auth'))->toBeNull();

        $response = $this->getJson('/api/status');
        $response->assertOk();
        expect($response->json('uptime'))->not->toHaveKey('downtime_minutes');
    });
});
