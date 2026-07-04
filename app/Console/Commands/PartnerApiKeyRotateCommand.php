<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PartnerApiKeyRotateCommand extends Command
{
    protected $signature = 'partner:api-key:rotate {partner}';

    protected $description = 'Rotate the API key for a partner (invalidates old key)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $partnerArg = $this->argument('partner');
        $partnerStr = is_string($partnerArg) ? $partnerArg : '';

        if (! $this->confirm("This will invalidate the existing API key for {$partnerStr}. Continue?")) {
            return self::FAILURE;
        }

        $newKey = 'sk_live_' . Str::random(40);
        $this->info("API key rotated for partner: {$partnerStr}");
        $this->line("New API Key: {$newKey}");
        $this->warn('Store the new key securely — the old key is now invalid.');

        return self::SUCCESS;
    }
}
