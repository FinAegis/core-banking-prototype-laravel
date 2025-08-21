<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AgentsDiscoveryControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the discovery endpoint returns all AGENTS.md files.
     */
    public function test_discovery_endpoint_returns_agents_files(): void
    {
        $response = $this->getJson('/api/agents/discovery');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'count',
                'agents' => [
                    '*' => [
                        'path',
                        'domain',
                        'type',
                        'size',
                        'last_modified',
                        'url',
                    ],
                ],
                'meta' => [
                    'generated_at',
                    'base_url',
                    'api_version',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify at least our created AGENTS.md files are found
        $agents = $response->json('agents');
        $domains = array_column($agents, 'domain');

        $this->assertContains('Exchange', $domains);
        $this->assertContains('Stablecoin', $domains);
        $this->assertContains('Lending', $domains);
    }

    /**
     * Test the content endpoint returns file content.
     */
    public function test_content_endpoint_returns_file_content(): void
    {
        // Get a valid path from discovery first
        $discovery = $this->getJson('/api/agents/discovery');
        $agents = $discovery->json('agents');

        // Find the Exchange domain AGENTS.md
        $exchangeAgent = collect($agents)->firstWhere('domain', 'Exchange');
        $this->assertNotNull($exchangeAgent, 'Exchange AGENTS.md not found');

        // Extract the path parameter from the URL
        $url = $exchangeAgent['url'];
        preg_match('/\/api\/agents\/content\/(.+)$/', $url, $matches);
        $encodedPath = $matches[1] ?? null;

        $this->assertNotNull($encodedPath, 'Could not extract encoded path from URL');

        $response = $this->getJson("/api/agents/content/{$encodedPath}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'path',
                'content',
                'metadata' => [
                    'domain',
                    'type',
                    'size',
                    'lines',
                    'last_modified',
                ],
            ])
            ->assertJson([
                'success' => true,
                'path'    => 'app/Domain/Exchange/AGENTS.md',
            ]);

        // Verify content contains expected text
        $content = $response->json('content');
        $this->assertStringContainsString('Exchange Domain - AI Agent Guide', $content);
        $this->assertStringContainsString('OrderMatchingService', $content);
    }

    /**
     * Test content endpoint returns 404 for non-existent file.
     */
    public function test_content_endpoint_returns_404_for_invalid_file(): void
    {
        $invalidPath = base64_encode('invalid/path/AGENTS.md');

        $response = $this->getJson("/api/agents/content/{$invalidPath}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'error'   => 'File not found',
            ]);
    }

    /**
     * Test content endpoint blocks directory traversal attempts.
     */
    public function test_content_endpoint_blocks_directory_traversal(): void
    {
        // Attempt directory traversal
        $maliciousPath = base64_encode('../../.env');

        $response = $this->getJson("/api/agents/content/{$maliciousPath}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'error'   => 'File not found',
            ]);
    }

    /**
     * Test content endpoint only returns AGENTS.md files.
     */
    public function test_content_endpoint_only_returns_agents_files(): void
    {
        // Try to access a non-AGENTS.md file
        $invalidPath = base64_encode('app/Models/User.php');

        $response = $this->getJson("/api/agents/content/{$invalidPath}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'error'   => 'File not found',
            ]);
    }

    /**
     * Test summary endpoint returns domain coverage.
     */
    public function test_summary_endpoint_returns_coverage_info(): void
    {
        $response = $this->getJson('/api/agents/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'total_files',
                'domains_with_agents',
                'all_domains',
                'coverage',
                'coverage_percentage',
                'recommendations',
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify our domains are included
        $domainsWithAgents = $response->json('domains_with_agents');
        $this->assertContains('Exchange', $domainsWithAgents);
        $this->assertContains('Stablecoin', $domainsWithAgents);
        $this->assertContains('Lending', $domainsWithAgents);

        // Verify coverage structure
        $coverage = $response->json('coverage');
        $this->assertArrayHasKey('domains', $coverage);
        $this->assertIsArray($coverage['domains']);
    }

    /**
     * Test discovery endpoint excludes vendor and node_modules.
     */
    public function test_discovery_excludes_vendor_directories(): void
    {
        $response = $this->getJson('/api/agents/discovery');

        $agents = $response->json('agents');

        // Ensure no paths contain vendor or node_modules
        foreach ($agents as $agent) {
            $this->assertStringNotContainsString('vendor/', $agent['path']);
            $this->assertStringNotContainsString('node_modules/', $agent['path']);
            $this->assertStringNotContainsString('storage/', $agent['path']);
            $this->assertStringNotContainsString('.git/', $agent['path']);
        }
    }

    /**
     * Test discovery endpoint sorts results properly.
     */
    public function test_discovery_sorts_by_type_and_domain(): void
    {
        $response = $this->getJson('/api/agents/discovery');

        $agents = $response->json('agents');

        if (count($agents) > 1) {
            // Check that root type comes first if it exists
            $types = array_column($agents, 'type');
            $rootIndex = array_search('root', $types);

            if ($rootIndex !== false) {
                $this->assertEquals(0, $rootIndex, 'Root type should be first');
            }

            // Within same type, check alphabetical domain sorting
            $domainAgents = array_filter($agents, fn ($a) => $a['type'] === 'domain');
            if (count($domainAgents) > 1) {
                $domains = array_column($domainAgents, 'domain');
                $sortedDomains = $domains;
                sort($sortedDomains);
                $this->assertEquals($sortedDomains, array_values($domains), 'Domains should be alphabetically sorted');
            }
        }
    }

    /**
     * Test that URLs in discovery are properly formatted.
     */
    public function test_discovery_urls_are_properly_formatted(): void
    {
        $response = $this->getJson('/api/agents/discovery');

        $agents = $response->json('agents');

        foreach ($agents as $agent) {
            $this->assertStringStartsWith('http', $agent['url']);
            $this->assertStringContainsString('/api/agents/content/', $agent['url']);

            // Verify the URL contains a base64 encoded path
            preg_match('/\/api\/agents\/content\/(.+)$/', $agent['url'], $matches);
            $this->assertNotEmpty($matches[1] ?? null);

            // Verify we can decode it back to the original path
            $decodedPath = base64_decode($matches[1]);
            $this->assertEquals($agent['path'], $decodedPath);
        }
    }
}
