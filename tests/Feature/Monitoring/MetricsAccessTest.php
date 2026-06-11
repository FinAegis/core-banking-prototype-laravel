<?php

declare(strict_types=1);

describe('health probes stay public', function () {
    it('serves /api/monitoring/ready without auth', function () {
        $this->getJson('/api/monitoring/ready')->assertOk();
    });

    it('serves /api/monitoring/alive without auth', function () {
        $this->getJson('/api/monitoring/alive')->assertOk();
    });

    it('serves /api/monitoring/ready without auth even in production', function () {
        $this->app['env'] = 'production';

        $this->getJson('/api/monitoring/ready')->assertOk();
    });

    it('serves /api/monitoring/alive without auth even in production', function () {
        $this->app['env'] = 'production';

        $this->getJson('/api/monitoring/alive')->assertOk();
    });
});

describe('metrics endpoints fail closed in production', function () {
    beforeEach(function () {
        $this->app['env'] = 'production';
        config([
            'monitoring.metrics_token'       => null,
            'monitoring.metrics_allowed_ips' => [],
        ]);
    });

    it('returns 403 for /api/monitoring/metrics when nothing is configured', function () {
        $this->getJson('/api/monitoring/metrics')->assertForbidden();
    });

    it('returns 403 for /api/monitoring/prometheus when nothing is configured', function () {
        $this->getJson('/api/monitoring/prometheus')->assertForbidden();
    });

    it('returns 403 for /api/metrics/prometheus when nothing is configured', function () {
        $this->getJson('/api/metrics/prometheus')->assertForbidden();
    });
});

describe('bearer token gate in production', function () {
    beforeEach(function () {
        $this->app['env'] = 'production';
        config([
            'monitoring.metrics_token'       => 'secret-token',
            'monitoring.metrics_allowed_ips' => [],
        ]);
    });

    it('allows access with the correct bearer token', function () {
        $this->getJson('/api/monitoring/metrics', ['Authorization' => 'Bearer secret-token'])
            ->assertOk();
    });

    it('rejects a wrong bearer token', function () {
        $this->getJson('/api/monitoring/metrics', ['Authorization' => 'Bearer wrong-token'])
            ->assertForbidden();
    });

    it('rejects a missing bearer token', function () {
        $this->getJson('/api/monitoring/metrics')->assertForbidden();
    });
});

describe('IP allowlist gate in production', function () {
    it('allows access when the client IP is allowlisted', function () {
        $this->app['env'] = 'production';
        config([
            'monitoring.metrics_token'       => null,
            'monitoring.metrics_allowed_ips' => ['127.0.0.1'],
        ]);

        $this->getJson('/api/monitoring/metrics')->assertOk();
    });

    it('rejects access when the client IP is not allowlisted', function () {
        $this->app['env'] = 'production';
        config([
            'monitoring.metrics_token'       => null,
            'monitoring.metrics_allowed_ips' => ['10.0.0.9'],
        ]);

        $this->getJson('/api/monitoring/metrics')->assertForbidden();
    });
});

describe('non-production convenience', function () {
    it('allows metrics access with nothing configured outside production', function () {
        config([
            'monitoring.metrics_token'       => null,
            'monitoring.metrics_allowed_ips' => [],
        ]);

        $this->getJson('/api/monitoring/metrics')->assertOk();
        $this->getJson('/api/metrics/prometheus')->assertOk();
    });
});

describe('slack alerting log config', function () {
    it('defaults the slack channel level to critical via LOG_SLACK_LEVEL', function () {
        // LOG_SLACK_LEVEL is unset in testing, so the default must hold — the
        // slack channel must never inherit the global LOG_LEVEL (e.g. warning).
        expect(config('logging.channels.slack.level'))->toBe('critical');
    });
});
