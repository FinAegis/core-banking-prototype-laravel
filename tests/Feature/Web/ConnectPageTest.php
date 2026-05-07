<?php

declare(strict_types=1);

it('renders the /connect page with all three onboarding steps', function (): void {
    $response = $this->get('/connect');

    $response->assertOk()
        ->assertSee('Connect', false)
        ->assertSeeText('Sign in to')
        ->assertSeeText('Add the connector to your AI')
        ->assertSeeText('Authorize');
});

it('exposes copy-paste config for every supported AI client', function (): void {
    $response = $this->get('/connect');

    $response->assertOk();
    foreach (['Claude Desktop', 'Claude.ai', 'Cursor', 'Continue.dev', 'Other (stdio)'] as $client) {
        $response->assertSeeText($client);
    }

    // The actual MCP endpoint URL must be present in the page.
    $mcpUrl = 'https://' . config('mcp.host', 'mcp.zelta.app') . '/mcp';
    $response->assertSee($mcpUrl, false);
});

it('links the sign-in CTA at the named login route', function (): void {
    $response = $this->get('/connect');

    $response->assertOk()
        ->assertSee(route('login'), false);
});

it('marks the page noindex so SEO weight stays on /features/mcp', function (): void {
    $response = $this->get('/connect');

    $response->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false);
});
