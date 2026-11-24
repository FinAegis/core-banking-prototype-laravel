<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
 * POST /api/ai/mcp/register validated the name against an `mcp_tools`
 * table that no migration creates, so every request ended in a server
 * error. The controller never stored a tool either: it answered with a
 * made-up tool_id. The endpoint is removed; the demo tool list and
 * execution endpoints stay.
 */
it('no longer serves the demo tool registration endpoint', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    $this->postJson('/api/ai/mcp/register', [
        'name'        => 'custom_tool',
        'description' => 'A custom tool',
        'endpoint'    => 'https://api.example.com/tool',
        'parameters'  => ['id' => ['type' => 'string']],
    ])->assertNotFound();

    expect(app('router')->getRoutes()->getByName('api.ai.mcp.register'))->toBeNull();
});

it('keeps the demo tool list working', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    $this->getJson('/api/ai/mcp/tools')->assertOk();
});
