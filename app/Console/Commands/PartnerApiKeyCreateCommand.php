<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PartnerApiKeyCreateCommand extends Command
{
    protected $signature = 'partner:api-key:create {partner} {--scopes=read,write : Comma-separated API scopes}';

    protected $description = 'Create an API key for a partner';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $partnerArg = $this->argument('partner');
        $partnerStr = is_string($partnerArg) ? $partnerArg : '';
        $scopesOption = $this->option('scopes');
        $scopes = explode(',', is_string($scopesOption) ? $scopesOption : 'read,write');
        $apiKey = 'sk_live_' . Str::random(40);
        $keyId = 'key_' . Str::random(12);

        $this->info("API Key created for partner: {$partnerStr}");
        $this->line("Key ID:  {$keyId}");
        $this->line("API Key: {$apiKey}");
        $this->line('Scopes:  ' . implode(', ', $scopes));
        $this->warn('Store the API key securely — it cannot be retrieved after this.');

        return self::SUCCESS;
    }
}
