<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generate AGENTS.md templates for domains.
 *
 * This command creates standardized AGENTS.md documentation
 * templates for AI agents to understand domain structure.
 */
class GenerateAgentsDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agents:generate 
                            {domain? : The domain name to generate docs for}
                            {--all : Generate for all existing domains}
                            {--force : Overwrite existing AGENTS.md files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate AGENTS.md documentation templates for AI agent integration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->generateForAllDomains();
        }

        $domain = $this->argument('domain');
        if (! $domain) {
            $domain = $this->askForDomain();
        }

        return $this->generateForDomain($domain);
    }

    /**
     * Generate AGENTS.md for all existing domains.
     */
    private function generateForAllDomains(): int
    {
        $domainPath = app_path('Domain');
        if (! is_dir($domainPath)) {
            $this->error('Domain directory not found at: ' . $domainPath);

            return Command::FAILURE;
        }

        $domains = File::directories($domainPath);
        $generated = 0;

        foreach ($domains as $domainDir) {
            $domainName = basename($domainDir);
            if ($domainName === 'Shared') {
                continue; // Skip shared domain
            }

            if ($this->generateForDomain($domainName) === Command::SUCCESS) {
                $generated++;
            }
        }

        $this->info("✅ Generated AGENTS.md for {$generated} domains");

        return Command::SUCCESS;
    }

    /**
     * Generate AGENTS.md for a specific domain.
     */
    private function generateForDomain(string $domain): int
    {
        $domainPath = app_path("Domain/{$domain}");

        if (! is_dir($domainPath)) {
            $this->error("Domain not found: {$domain}");

            return Command::FAILURE;
        }

        $agentsFile = "{$domainPath}/AGENTS.md";

        if (File::exists($agentsFile) && ! $this->option('force')) {
            $this->warn("AGENTS.md already exists for {$domain}. Use --force to overwrite.");

            return Command::FAILURE;
        }

        $content = $this->generateContent($domain, $domainPath);
        File::put($agentsFile, $content);

        $this->info("✅ Generated AGENTS.md for domain: {$domain}");
        $this->line("   Location: {$agentsFile}");

        return Command::SUCCESS;
    }

    /**
     * Ask user to select a domain.
     */
    private function askForDomain(): string
    {
        $domainPath = app_path('Domain');
        $domains = array_map('basename', File::directories($domainPath));
        $domains = array_filter($domains, fn ($d) => $d !== 'Shared');
        $domains = array_values($domains); // Re-index array

        /** @var string $result */
        $result = $this->choice('Which domain would you like to generate docs for?', $domains);

        return $result;
    }

    /**
     * Generate AGENTS.md content based on domain structure.
     */
    private function generateContent(string $domain, string $domainPath): string
    {
        $services = $this->scanDirectory("{$domainPath}/Services", 'Service');
        $aggregates = $this->scanDirectory("{$domainPath}/Aggregates", 'Aggregate');
        $workflows = $this->scanDirectory("{$domainPath}/Workflows", 'Workflow');
        $events = $this->scanDirectory("{$domainPath}/Events", 'Event');
        $models = $this->scanDirectory("{$domainPath}/Models", '');
        $repositories = $this->scanDirectory("{$domainPath}/Repositories", 'Repository');
        $activities = $this->scanDirectory("{$domainPath}/Activities", 'Activity');
        $projectors = $this->scanDirectory("{$domainPath}/Projectors", 'Projector');

        $content = "# {$domain} Domain - AI Agent Guide\n\n";
        $content .= "## Purpose\n";
        $content .= $this->generatePurpose($domain);
        $content .= "\n\n";

        $content .= "## Key Components\n\n";

        if (! empty($aggregates)) {
            $content .= "### Aggregates\n";
            foreach ($aggregates as $aggregate) {
                $content .= "- **{$aggregate}**: [Description needed]\n";
            }
            $content .= "\n";
        }

        if (! empty($services)) {
            $content .= "### Services\n";
            foreach ($services as $service) {
                $content .= "- **{$service}**: [Description needed]\n";
            }
            $content .= "\n";
        }

        if (! empty($workflows)) {
            $content .= "### Workflows\n";
            foreach ($workflows as $workflow) {
                $content .= "- **{$workflow}**: [Description needed]\n";
            }
            $content .= "\n";
        }

        if (! empty($activities)) {
            $content .= "### Activities (Workflow Steps)\n";
            foreach ($activities as $activity) {
                $content .= "- **{$activity}**: [Description needed]\n";
            }
            $content .= "\n";
        }

        if (! empty($events)) {
            $content .= "### Events (Event Sourcing)\n";
            $content .= "All events extend `ShouldBeStored`:\n";
            foreach ($events as $event) {
                $content .= "- {$event}\n";
            }
            $content .= "\n";
        }

        if (! empty($projectors)) {
            $content .= "### Projectors\n";
            foreach ($projectors as $projector) {
                $content .= "- **{$projector}**: [Description needed]\n";
            }
            $content .= "\n";
        }

        if (! empty($models)) {
            $content .= "### Models\n";
            foreach ($models as $model) {
                $content .= "- **{$model}**: [Description needed]\n";
            }
            $content .= "\n";
        }

        if (! empty($repositories)) {
            $content .= "### Repositories\n";
            foreach ($repositories as $repository) {
                $content .= "- **{$repository}**: [Description needed]\n";
            }
            $content .= "\n";
        }

        $content .= "## Common Tasks\n\n";
        $content .= "[Add common task examples with code snippets]\n\n";

        $content .= "## Testing\n\n";
        $content .= "### Key Test Files\n";
        $content .= "- `tests/Unit/Domain/{$domain}/`\n";
        $content .= "- `tests/Feature/{$domain}/`\n\n";
        $content .= "### Running Tests\n";
        $content .= "```bash\n";
        $content .= "# Run all {$domain} domain tests\n";
        $content .= "./vendor/bin/pest tests/Unit/Domain/{$domain} tests/Feature/{$domain}\n";
        $content .= "```\n\n";

        $content .= "## Database\n\n";
        $content .= "### Main Tables\n";
        $content .= "[List main database tables used by this domain]\n\n";
        $content .= "### Migrations\n";
        $content .= "Located in `database/migrations/`\n\n";

        $content .= "## API Endpoints\n\n";
        $content .= "[List API endpoints related to this domain]\n\n";

        $content .= "## Configuration\n\n";
        $content .= "### Environment Variables\n";
        $content .= "```env\n";
        $content .= "# {$domain} Configuration\n";
        $content .= "[Add relevant environment variables]\n";
        $content .= "```\n\n";

        $content .= "## Best Practices\n\n";
        $content .= "[Add domain-specific best practices]\n\n";

        $content .= "## Common Issues\n\n";
        $content .= "[Add common issues and solutions]\n\n";

        $content .= "## AI Agent Tips\n\n";
        $content .= "[Add tips for AI agents working with this domain]\n";

        return $content;
    }

    /**
     * Generate purpose description based on domain name.
     */
    private function generatePurpose(string $domain): string
    {
        $purposes = [
            'Account'    => 'This domain manages user accounts, multi-asset balances, and account-related operations.',
            'Exchange'   => 'This domain handles cryptocurrency and fiat exchange operations, order matching, and liquidity pools.',
            'Stablecoin' => 'This domain manages stablecoin operations including minting, burning, redemption, and reserve management.',
            'Lending'    => 'This domain handles peer-to-peer lending operations including loan origination, credit scoring, and repayment processing.',
            'Wallet'     => 'This domain manages blockchain wallet operations, key management, and cryptocurrency transactions.',
            'Treasury'   => 'This domain handles treasury management, liquidity provision, and yield optimization.',
            'CGO'        => 'This domain manages Continuous Growth Offering token sales and distribution.',
            'Governance' => 'This domain handles voting, proposals, and decentralized governance mechanisms.',
            'Compliance' => 'This domain manages KYC/AML compliance, regulatory reporting, and risk assessment.',
            'AI'         => 'This domain handles AI agent integration, natural language processing, and intelligent automation.',
        ];

        return $purposes[$domain] ?? "This domain handles {$domain}-related operations and business logic.";
    }

    /**
     * Scan directory for specific file types.
     */
    private function scanDirectory(string $path, string $suffix): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = File::files($path);
        $results = [];

        foreach ($files as $file) {
            $filename = $file->getFilenameWithoutExtension();
            if (empty($suffix) || Str::endsWith($filename, $suffix)) {
                $results[] = $filename;
            }
        }

        sort($results);

        return $results;
    }
}
