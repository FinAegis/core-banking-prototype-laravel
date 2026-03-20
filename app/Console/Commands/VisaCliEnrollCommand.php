<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\VisaCli\Services\VisaCliCardEnrollmentService;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class VisaCliEnrollCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'visa:enroll
                            {--user= : User ID or email to enroll a card for}';

    /**
     * @var string
     */
    protected $description = 'Enroll a card for Visa CLI payments (non-production only)';

    public function handle(VisaCliCardEnrollmentService $enrollmentService): int
    {
        if (app()->environment('production')) {
            $this->error('This command is not available in production. Use the API instead.');

            return 1;
        }

        if (! config('visacli.enabled', false)) {
            $this->warn('Visa CLI integration is disabled. Set VISACLI_ENABLED=true to enable.');

            return 1;
        }

        $userInput = $this->option('user');
        if (empty($userInput)) {
            $userInput = $this->ask('Enter user ID or email');
        }

        $user = is_numeric($userInput)
            ? User::find($userInput)
            : User::where('email', $userInput)->first();

        if ($user === null) {
            $this->error("User not found: {$userInput}");

            return 1;
        }

        $this->info("Enrolling card for user: {$user->email} (ID: {$user->id})");

        try {
            $card = $enrollmentService->enrollCard((string) $user->id);

            $this->info('Card enrolled successfully!');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Card ID', $card->id],
                    ['Card Identifier', $card->card_identifier],
                    ['Last 4', $card->last4],
                    ['Network', $card->network],
                    ['Status', is_string($card->status) ? $card->status : $card->status->value],
                    ['GitHub User', $card->github_username ?? 'N/A'],
                ]
            );

            return 0;
        } catch (Throwable $e) {
            $this->error("Enrollment failed: {$e->getMessage()}");

            return 1;
        }
    }
}
