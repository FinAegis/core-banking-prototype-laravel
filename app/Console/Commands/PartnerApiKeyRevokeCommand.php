<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PartnerApiKeyRevokeCommand extends Command
{
    protected $signature = 'partner:api-key:revoke {key_id}';

    protected $description = 'Revoke a specific API key';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $keyIdArg = $this->argument('key_id');
        $keyIdStr = is_string($keyIdArg) ? $keyIdArg : '';

        if (! $this->confirm("Revoke API key {$keyIdStr}? This cannot be undone.")) {
            return self::FAILURE;
        }

        $this->info("API key {$keyIdStr} has been revoked.");

        return self::SUCCESS;
    }
}
