<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PartnerApiKeyListCommand extends Command
{
    protected $signature = 'partner:api-key:list {partner}';

    protected $description = 'List API keys for a partner';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $partnerId = $this->argument('partner');
        $partnerStr = is_string($partnerId) ? $partnerId : '';
        $this->info("API keys for partner: {$partnerStr}");
        $this->line('No API keys found. Use partner:api-key:create to generate one.');

        return self::SUCCESS;
    }
}
