<?php

declare(strict_types=1);

use function Pest\Laravel\get;

describe('AI Framework Feature Page', function (): void {
    it('renders the AI framework page', function (): void {
        $response = get('/features/ai-framework');

        $response->assertOk();
        $response->assertSee('AI Framework');
    });

    it('displays the MCP tool count matching the live catalog', function (): void {
        $response = get('/features/ai-framework');

        $response->assertOk();
        $response->assertSee(count(config('mcp.tools')) . ' MCP Tools');
    });

    it('displays all six agents', function (): void {
        $response = get('/features/ai-framework');

        $response->assertOk();
        $response->assertSee('General Agent');
        $response->assertSee('Compliance Agent');
        $response->assertSee('Financial Agent');
        $response->assertSee('Trading Agent');
        $response->assertSee('Transfer Agent');
        $response->assertSee('Orchestrator');
    });

    it('displays ML detection models', function (): void {
        $response = get('/features/ai-framework');

        $response->assertOk();
        $response->assertSee('Statistical Analysis');
        $response->assertSee('Behavioral Profiling');
        $response->assertSee('Velocity Checks');
        $response->assertSee('Geo-Based Detection');
        $response->assertSee('Ensemble Scoring');
    });

    it('links to agent protocol page', function (): void {
        $response = get('/features/ai-framework');

        $response->assertOk();
        $response->assertSee('agent-protocol');
    });

    it('includes SEO meta tags', function (): void {
        $response = get('/features/ai-framework');

        $response->assertOk();
        $response->assertSee('Multi-Agent Intelligence', false);
    });
});
