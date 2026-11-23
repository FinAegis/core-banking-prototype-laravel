<?php

declare(strict_types=1);

use App\Models\User;

/*
 * These admin pages crashed when opened: Settings and Bank Allocations
 * imported models from namespaces that do not exist, Bank Operations fed a
 * plain collection to a Filament table and called methods Filament 3 removed,
 * and Audit Log was an empty scaffold on a model that does not exist.
 */

beforeEach(function (): void {
    $this->actingAs(User::factory()->withAdminRole()->create());
});

it('opens the settings page', function (): void {
    $this->get('/admin/settings')->assertOk();
});

it('opens the bank allocations list', function (): void {
    $this->get('/admin/user-bank-preferences')->assertOk();
});

it('no longer registers the pages that could never work', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())->map->uri()->all();

    expect($routes)->not->toContain('admin/bank-operations')
        ->and($routes)->not->toContain('admin/audit-logs');
});
