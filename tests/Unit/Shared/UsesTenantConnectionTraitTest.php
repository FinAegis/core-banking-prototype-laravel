<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->model = new class () extends Model {
        use UsesTenantConnection;
    };
});

it('returns null (default connection) when running under testing env without override', function () {
    Config::set('app.env', 'testing');
    Config::set('database.force_real_tenant_connection', false);

    expect($this->model->getConnectionName())->toBeNull();
});

it('returns "tenant" when force_real_tenant_connection is true even under testing', function () {
    Config::set('app.env', 'testing');
    Config::set('database.force_real_tenant_connection', true);

    expect($this->model->getConnectionName())->toBe('tenant');
});

it('returns "tenant" when env is not testing regardless of override flag', function () {
    Config::set('app.env', 'production');
    Config::set('database.force_real_tenant_connection', false);

    expect($this->model->getConnectionName())->toBe('tenant');
});
