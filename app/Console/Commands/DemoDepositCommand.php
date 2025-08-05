<?php

namespace App\Console\Commands;

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\AccountService;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DemoDepositCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:deposit 
                            {email : The email of the user to deposit to}
                            {amount : The amount to deposit}
                            {--asset=USD : The asset code (USD, EUR, GBP, GCU)}
                            {--description=Demo deposit : Description for the transaction}
                            {--instant : Skip queue processing and apply instantly (demo mode only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a demo deposit for testing purposes (only available in demo/testing mode)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if demo/testing mode is enabled
        if (! in_array(config('app.env'), ['local', 'testing', 'demo'])) {
            $this->error('This command is only available in local, testing, or demo environments.');

            return 1;
        }

        $email = $this->argument('email');
        $amount = $this->argument('amount');
        $assetCode = strtoupper($this->option('asset'));
        $description = $this->option('description');
        $instant = $this->option('instant');

        // Validate amount
        if (! is_numeric($amount) || $amount <= 0) {
            $this->error('Amount must be a positive number.');

            return 1;
        }

        // Find user
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User with email {$email} not found.");

            return 1;
        }

        // Find or create account
        $account = $user->accounts()->first();
        if (! $account) {
            $this->info('User has no account. Creating one...');

            $accountService = app(AccountService::class);
            $accountData = new \App\Domain\Account\DataObjects\Account(
                name: 'Demo Account',
                userUuid: $user->uuid
            );

            $accountService->create($accountData);

            // Process queue to ensure account is created (unless instant mode)
            if (! $instant) {
                $this->call(
                    'queue:work',
                    [
                        '--stop-when-empty' => true,
                        '--queue'           => 'default,events,ledger,transactions',
                    ]
                );
            }

            // Refresh to get the created account
            $account = $user->accounts()->first();

            if (! $account) {
                $this->error('Failed to create account.');

                return 1;
            }
        }

        // Verify asset exists
        $asset = Asset::where('code', $assetCode)->where('is_active', true)->first();
        if (! $asset) {
            $this->error("Asset {$assetCode} not found or not active.");
            $this->info('Available assets: ' . Asset::where('is_active', true)->pluck('code')->implode(', '));

            return 1;
        }

        // Convert amount to smallest unit (cents)
        $amountInCents = (int) ($amount * 100);

        $this->info('Processing deposit' . ($instant ? ' (instant mode)' : '') . '...');
        $this->info("User: {$user->name} ({$user->email})");
        $this->info("Account: {$account->name} (UUID: {$account->uuid})");
        $this->info("Amount: {$amount} {$assetCode} ({$amountInCents} cents)");

        try {
            $transactionId = null;

            if ($instant && config('demo.mode')) {
                // Use instant demo deposit service
                $paymentService = app(\App\Domain\Payment\Contracts\PaymentServiceInterface::class);

                if ($paymentService instanceof \App\Domain\Payment\Services\DemoPaymentService) {
                    $this->info('Using instant demo payment service...');

                    $result = $paymentService->processStripeDeposit([
                        'account_uuid'   => $account->uuid,
                        'amount'         => $amountInCents,
                        'currency'       => strtolower($assetCode),
                        'description'    => $description,
                        'payment_method' => 'demo_instant',
                    ]);

                    $transactionId = $result;
                    $this->info('✅ Instant deposit successful!');
                } else {
                    $this->warn('Instant mode is only available in demo mode. Falling back to regular processing...');
                    $instant = false;
                }
            }

            if (! $instant || ! config('demo.mode')) {
                // Regular event sourcing deposit
                $ledger = app(LedgerAggregate::class);
                $transactionId = Str::uuid()->toString();

                $ledger->retrieve($account->uuid)
                    ->addMoney(
                        assetCode: $assetCode,
                        amount: $amountInCents,
                        description: $description,
                        transactionId: $transactionId,
                        metadata: [
                            'type'        => 'demo_deposit',
                            'created_by'  => 'console_command',
                            'environment' => config('app.env'),
                            'instant'     => $instant,
                        ]
                    )
                    ->persist();

                // Process event queue
                $this->call(
                    'queue:work',
                    [
                        '--stop-when-empty' => true,
                        '--queue'           => 'events,ledger',
                    ]
                );

                $this->info('✅ Deposit successful!');
            }

            $this->info("Transaction ID: {$transactionId}");

            // Show updated balance
            $account->refresh();
            $balance = $account->getBalanceForAsset($assetCode);
            if ($balance) {
                $formattedBalance = number_format($balance->balance / 100, 2);
                $this->info("New balance: {$formattedBalance} {$assetCode}");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create deposit: ' . $e->getMessage());

            return 1;
        }
    }
}
