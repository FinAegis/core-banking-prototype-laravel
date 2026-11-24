<?php

declare(strict_types=1);

use App\Models\SystemHealthCheck;
use Illuminate\Support\Facades\DB;

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

    it('writes no health check rows when people view the page', function (): void {
        $this->get('/status')->assertOk();
        $this->get('/status')->assertOk();
        $this->getJson('/api/status')->assertOk();

        expect(SystemHealthCheck::query()->count())->toBe(0);
    });

    it('reuses the live check results for 60 seconds', function (): void {
        $pings = 0;
        DB::listen(function ($query) use (&$pings): void {
            if (strtolower(trim($query->sql)) === 'select 1') {
                $pings++;
            }
        });

        $this->get('/status')->assertOk();
        $this->get('/status')->assertOk();
        expect($pings)->toBe(1);

        $this->travel(61)->seconds();
        $this->get('/status')->assertOk();
        expect($pings)->toBe(2);
    });

    it('does not report an uptime figure without checks', function (): void {
        expect(SystemHealthCheck::calculateUptime('auth'))->toBeNull();

        $response = $this->getJson('/api/status');
        $response->assertOk();
        expect($response->json('uptime'))->not->toHaveKey('downtime_minutes');
    });
});
