<?php

namespace App\Console\Commands;

use App\Services\Cgo\PaymentVerificationService;
use Illuminate\Console\Command;

class VerifyCgoPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cgo:verify-payments 
                            {--expired : Also handle expired payments}
                            {--investment= : Verify a specific investment by UUID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify pending CGO investment payments';

    /**
     * Execute the console command.
     */
    public function handle(PaymentVerificationService $verificationService): int
    {
        $this->info('Starting CGO payment verification...');

        // Verify specific investment if provided
        if ($investmentUuid = $this->option('investment')) {
            $investment = \App\Models\CgoInvestment::where('uuid', $investmentUuid)->first();
            
            if (!$investment) {
                $this->error("Investment with UUID {$investmentUuid} not found.");
                return Command::FAILURE;
            }

            $verified = $verificationService->verifyPayment($investment);
            
            if ($verified) {
                $this->info("Payment verified successfully for investment {$investmentUuid}");
            } else {
                $this->warn("Payment could not be verified for investment {$investmentUuid}");
            }

            return Command::SUCCESS;
        }

        // Verify all pending payments
        $confirmed = $verificationService->verifyPendingPayments();
        $this->info("Verified {$confirmed} payments.");

        // Handle expired payments if requested
        if ($this->option('expired')) {
            $expired = $verificationService->handleExpiredPayments();
            $this->info("Handled {$expired} expired payments.");
        }

        $this->info('Payment verification completed.');

        return Command::SUCCESS;
    }
}