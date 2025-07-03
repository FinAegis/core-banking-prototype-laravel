<?php

namespace App\Http\Controllers;

use App\Models\CgoNotification;
use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\CgoNotificationReceived;
use App\Mail\CgoInvestmentReceived;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Services\Email\SubscriberEmailService;
use App\Services\Cgo\StripePaymentService;

class CgoController extends Controller
{
    public function notify(Request $request, SubscriberEmailService $emailService)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);
        
        // Check if email already exists in CGO notifications
        $existing = CgoNotification::where('email', $validated['email'])->first();
        
        if (!$existing) {
            CgoNotification::create([
                'email' => $validated['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            // Also add to subscriber list
            try {
                $emailService->subscribe(
                    $validated['email'],
                    Subscriber::SOURCE_CGO,
                    ['cgo_early_access', 'investment_opportunities'],
                    $request->ip(),
                    $request->userAgent()
                );
            } catch (\Exception $e) {
                \Log::error('Failed to add CGO subscriber: ' . $e->getMessage());
            }
            
            // Send confirmation email (keep existing functionality)
            try {
                Mail::to($validated['email'])->send(new CgoNotificationReceived($validated['email']));
            } catch (\Exception $e) {
                // Log error but don't fail the request
                \Log::error('Failed to send CGO notification email: ' . $e->getMessage());
            }
        }
        
        return redirect()->back()->with('success', 'Thank you! We\'ll notify you when the CGO launches.');
    }
    
    public function showInvest()
    {
        $currentRound = CgoPricingRound::getCurrentRound();
        $userInvestments = auth()->user()->cgoInvestments()->orderBy('created_at', 'desc')->get();
        
        return view('cgo.invest', compact('currentRound', 'userInvestments'));
    }
    
    public function invest(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'payment_method' => 'required|in:crypto,bank_transfer,card',
            'crypto_currency' => 'required_if:payment_method,crypto|in:BTC,ETH,USDT,USDC',
            'terms' => 'required|accepted',
        ]);
        
        $currentRound = CgoPricingRound::getCurrentRound();
        
        if (!$currentRound) {
            return redirect()->back()->withErrors(['error' => 'No active investment round at the moment.']);
        }
        
        // Calculate shares and ownership
        $shares = $validated['amount'] / $currentRound->share_price;
        $totalShares = 1000000; // Total platform shares
        $ownershipPercentage = ($shares / $totalShares) * 100;
        
        // Check 1% max ownership rule
        $userTotalOwnership = auth()->user()->cgoInvestments()
            ->where('status', 'confirmed')
            ->sum('ownership_percentage');
            
        if (($userTotalOwnership + $ownershipPercentage) > 1.0) {
            return redirect()->back()->withErrors(['amount' => 'This investment would exceed the 1% maximum ownership limit per round.']);
        }
        
        // Check available shares in round
        if ($shares > $currentRound->remaining_shares) {
            return redirect()->back()->withErrors(['amount' => 'Not enough shares available in this round.']);
        }
        
        // Determine tier
        $tier = 'bronze';
        if ($validated['amount'] >= 10000) {
            $tier = 'gold';
        } elseif ($validated['amount'] >= 1000) {
            $tier = 'silver';
        }
        
        DB::beginTransaction();
        
        try {
            // Create investment record
            $investment = CgoInvestment::create([
                'user_id' => auth()->id(),
                'round_id' => $currentRound->id,
                'amount' => $validated['amount'],
                'currency' => 'USD',
                'share_price' => $currentRound->share_price,
                'shares_purchased' => $shares,
                'ownership_percentage' => $ownershipPercentage,
                'tier' => $tier,
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'metadata' => [
                    'crypto_currency' => $validated['crypto_currency'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
            
            DB::commit();
            
            // Generate payment details based on method
            if ($validated['payment_method'] === 'crypto') {
                return $this->processCryptoPayment($investment, $validated['crypto_currency']);
            } elseif ($validated['payment_method'] === 'bank_transfer') {
                return $this->processBankTransfer($investment);
            } else {
                return $this->processCardPayment($investment);
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('CGO investment error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'An error occurred processing your investment. Please try again.']);
        }
    }
    
    private function processCryptoPayment($investment, $cryptoCurrency)
    {
        // Get crypto addresses from configuration
        $cryptoAddresses = [
            'BTC' => config('cgo.crypto_addresses.btc', 'NOT-CONFIGURED'),
            'ETH' => config('cgo.crypto_addresses.eth', 'NOT-CONFIGURED'),
            'USDT' => config('cgo.crypto_addresses.usdt', 'NOT-CONFIGURED'),
            'USDC' => config('cgo.crypto_addresses.usdc', 'NOT-CONFIGURED'),
        ];
        
        $cryptoAddress = $cryptoAddresses[$cryptoCurrency];
        
        // Check if crypto is properly configured
        if ($cryptoAddress === 'NOT-CONFIGURED' || empty($cryptoAddress)) {
            if (!config('cgo.production_crypto_enabled') && app()->environment('production')) {
                throw new \Exception("Crypto payments are not enabled in production.");
            }
            // In test environments, use test addresses
            if (app()->environment(['local', 'staging'])) {
                $testAddresses = [
                    'BTC' => 'TEST-BTC-ADDRESS-DO-NOT-USE',
                    'ETH' => 'TEST-ETH-ADDRESS-DO-NOT-USE',
                    'USDT' => 'TEST-USDT-ADDRESS-DO-NOT-USE',
                    'USDC' => 'TEST-USDC-ADDRESS-DO-NOT-USE',
                ];
                $cryptoAddress = $testAddresses[$cryptoCurrency];
            } else {
                throw new \Exception("Crypto address for {$cryptoCurrency} is not configured.");
            }
        }
        
        $investment->update([
            'crypto_address' => $cryptoAddress,
        ]);
        
        // TODO: Integrate with Coinbase Commerce or similar for real crypto payments
        
        return view('cgo.crypto-payment', [
            'investment' => $investment,
            'cryptoCurrency' => $cryptoCurrency,
            'cryptoAddress' => $cryptoAddress,
            'amount' => $investment->amount, // TODO: Convert to crypto amount based on current rates
        ]);
    }
    
    private function processBankTransfer($investment)
    {
        return view('cgo.bank-transfer', [
            'investment' => $investment,
            'bankDetails' => [
                'bank_name' => 'FinAegis Holdings Bank',
                'account_name' => 'FinAegis CGO Investment Account',
                'account_number' => 'CGO-' . str_pad($investment->id, 8, '0', STR_PAD_LEFT),
                'swift_code' => 'FINAGCGO',
                'reference' => 'CGO-' . $investment->uuid,
            ],
        ]);
    }
    
    private function processCardPayment($investment)
    {
        try {
            $stripeService = new StripePaymentService();
            $session = $stripeService->createCheckoutSession($investment);
            
            return redirect($session->url);
        } catch (\Exception $e) {
            \Log::error('Error creating Stripe checkout session: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Unable to process card payment. Please try another payment method.']);
        }
    }
    
    public function paymentSuccess(Request $request, $investmentUuid)
    {
        $investment = CgoInvestment::where('uuid', $investmentUuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        // Verify payment with Stripe
        $stripeService = new StripePaymentService();
        $paymentVerified = $stripeService->verifyPayment($investment);
        
        if ($paymentVerified) {
            // Send confirmation email
            try {
                Mail::to($investment->user->email)->send(new CgoInvestmentReceived($investment));
            } catch (\Exception $e) {
                \Log::error('Failed to send investment confirmation email: ' . $e->getMessage());
            }
            
            return view('cgo.payment-success', [
                'investment' => $investment,
                'message' => 'Your investment has been successfully processed!',
            ]);
        }
        
        return view('cgo.payment-pending', [
            'investment' => $investment,
            'message' => 'Your payment is being processed. You will receive a confirmation email once completed.',
        ]);
    }
    
    public function paymentCancel(Request $request, $investmentUuid)
    {
        $investment = CgoInvestment::where('uuid', $investmentUuid)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();
        
        // Update investment status
        $investment->update([
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        
        return redirect()->route('cgo.invest')->with('info', 'Your investment has been cancelled.');
    }
    
    public function downloadCertificate($uuid)
    {
        $investment = CgoInvestment::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->where('status', 'confirmed')
            ->firstOrFail();
            
        if (!$investment->certificate_number) {
            abort(404, 'Certificate not yet issued');
        }
        
        // Generate PDF certificate (simplified version)
        $pdf = \PDF::loadView('cgo.certificate', compact('investment'));
        
        return $pdf->download('FinAegis-CGO-Certificate-' . $investment->certificate_number . '.pdf');
    }
}