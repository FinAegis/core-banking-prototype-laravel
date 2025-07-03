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
use App\Services\Cgo\CoinbaseCommerceService;
use App\Services\Cgo\CgoKycService;
use App\Jobs\VerifyCgoPayment;

class CgoController extends Controller
{
    /**
     * Display user's investments
     */
    public function myInvestments()
    {
        $investments = CgoInvestment::where('user_id', auth()->id())
            ->with('round')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        $summary = [
            'total_invested' => CgoInvestment::where('user_id', auth()->id())
                ->whereIn('status', ['confirmed', 'pending'])
                ->sum('amount'),
            'total_shares' => CgoInvestment::where('user_id', auth()->id())
                ->where('status', 'confirmed')
                ->sum('shares_purchased'),
            'total_ownership' => CgoInvestment::where('user_id', auth()->id())
                ->where('status', 'confirmed')
                ->sum('ownership_percentage'),
            'currency' => 'USD',
        ];
        
        return view('cgo.investments', compact('investments', 'summary'));
    }
    
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
                \Log::error('Failed to send CGO notification email: ' . $e->getMessage());
            }
        }
        
        return redirect()->route('cgo.notify-success');
    }
    
    public function notifySuccess()
    {
        return view('cgo.notify-success');
    }
    
    public function invest(CgoKycService $kycService)
    {
        $currentRound = CgoPricingRound::where('is_active', true)->first();
        
        if (!$currentRound) {
            return view('cgo.closed');
        }
        
        $user = auth()->user();
        $kycStatus = null;
        
        if ($user) {
            // Create temporary investment to check KYC requirements for each package
            $kycRequirements = [];
            foreach (config('cgo.packages', []) as $key => $package) {
                $tempInvestment = new CgoInvestment([
                    'user_id' => $user->id,
                    'amount' => $package['amount'],
                ]);
                $kycRequirements[$key] = $kycService->checkKycRequirements($tempInvestment);
            }
            
            $kycStatus = [
                'user_kyc_status' => $user->kyc_status,
                'user_kyc_level' => $user->kyc_level,
                'requirements_by_package' => $kycRequirements,
            ];
        }
        
        return view('cgo.invest', [
            'currentRound' => $currentRound,
            'packages' => config('cgo.packages', []),
            'kycStatus' => $kycStatus,
        ]);
    }
    
    public function processInvestment(Request $request, CgoKycService $kycService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'package' => 'required|in:' . implode(',', array_keys(config('cgo.packages', []))),
            'payment_method' => 'required|in:card,crypto,bank_transfer',
            'crypto_currency' => 'required_if:payment_method,crypto|in:BTC,ETH,USDT,USDC',
            'terms_accepted' => 'required|accepted',
        ]);
        
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login')->with('message', 'Please login to continue with your investment.');
        }
        
        $currentRound = CgoPricingRound::where('is_active', true)->first();
        
        if (!$currentRound) {
            return redirect()->route('cgo')->withErrors(['error' => 'Investment round is currently closed.']);
        }
        
        $packages = config('cgo.packages', []);
        
        if (!isset($packages[$validated['package']])) {
            return redirect()->back()->withErrors(['error' => 'Invalid package selected.']);
        }
        
        $packageAmount = $packages[$validated['package']]['amount'];
        
        // Check investment limits
        if ($packageAmount < config('cgo.min_investment', 1000) || $packageAmount > config('cgo.max_investment', 1000000)) {
            return redirect()->back()->withErrors(['error' => 'Investment amount is outside allowed limits.']);
        }
        
        DB::beginTransaction();
        
        try {
            // Create investment record
            $investment = CgoInvestment::create([
                'user_id' => $user->id,
                'round_id' => $currentRound->id,
                'tier' => $validated['package'],
                'amount' => $packageAmount,
                'currency' => 'USD',
                'share_price' => $currentRound->share_price,
                'shares_purchased' => $packageAmount / $currentRound->share_price,
                'ownership_percentage' => ($packageAmount / $currentRound->share_price) / $currentRound->max_shares_available * 100,
                'email' => $validated['email'],
                'payment_method' => $validated['payment_method'],
                'crypto_currency' => $validated['crypto_currency'] ?? null,
                'status' => 'pending',
                'metadata' => [
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'terms_accepted_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
            
            // Verify KYC requirements
            $kycVerified = $kycService->verifyInvestor($investment);
            
            if (!$kycVerified) {
                DB::commit(); // Keep the investment record but mark it as requiring KYC
                
                return redirect()->route('cgo.kyc.status')->withErrors([
                    'kyc_required' => 'KYC verification is required for this investment amount. Please complete the verification process to continue.',
                    'investment_id' => $investment->uuid,
                ]);
            }
            
            DB::commit();
            
            // Process payment based on method
            switch ($validated['payment_method']) {
                case 'card':
                    return $this->processCardPayment($investment);
                    
                case 'crypto':
                    return $this->processCryptoPayment($investment, $validated['crypto_currency']);
                    
                case 'bank_transfer':
                    return $this->processBankTransfer($investment);
                    
                default:
                    throw new \Exception('Invalid payment method');
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('CGO investment error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'An error occurred processing your investment. Please try again.']);
        }
    }
    
    private function processCryptoPayment($investment, $cryptoCurrency)
    {
        // Check if Coinbase Commerce is enabled and configured
        if (config('cgo.payment_processors.coinbase_commerce.enabled', false) && !empty(config('services.coinbase_commerce.api_key'))) {
            try {
                $coinbaseService = new CoinbaseCommerceService();
                $charge = $coinbaseService->createCharge($investment);
                
                // Redirect to Coinbase Commerce hosted checkout
                return redirect($charge['hosted_url']);
            } catch (\Exception $e) {
                \Log::error('Coinbase Commerce error: ' . $e->getMessage());
                // Fall back to manual crypto payment
            }
        }
        
        // Manual crypto payment fallback
        // Get crypto addresses from environment configuration
        $cryptoAddresses = [
            'BTC' => config('cgo.crypto_addresses.btc', 'NOT-CONFIGURED'),
            'ETH' => config('cgo.crypto_addresses.eth', 'NOT-CONFIGURED'),
            'USDT' => config('cgo.crypto_addresses.usdt', 'NOT-CONFIGURED'),
            'USDC' => config('cgo.crypto_addresses.usdc', 'NOT-CONFIGURED'),
        ];
        
        $cryptoAddress = $cryptoAddresses[$cryptoCurrency] ?? 'NOT-CONFIGURED';
        
        // Safety check - prevent using unconfigured addresses
        if ($cryptoAddress === 'NOT-CONFIGURED' || empty($cryptoAddress)) {
            throw new \Exception("Crypto address for {$cryptoCurrency} is not configured. Please set CGO_{$cryptoCurrency}_ADDRESS in your .env file.");
        }
        
        // Additional safety for production
        if (app()->environment('production') && !config('cgo.production_crypto_enabled', false)) {
            throw new \Exception("Crypto payments are not enabled in production. Please use card or bank transfer.");
        }
        
        // Display warning in non-production environments
        if (!app()->environment('production')) {
            \Log::warning("CGO Crypto payment in non-production environment", [
                'investment_id' => $investment->id,
                'currency' => $cryptoCurrency,
                'address' => $cryptoAddress,
                'environment' => app()->environment()
            ]);
        }
        
        $investment->update([
            'crypto_address' => $cryptoAddress,
        ]);
        
        return view('cgo.crypto-payment', [
            'investment' => $investment,
            'cryptoCurrency' => $cryptoCurrency,
            'cryptoAddress' => $cryptoAddress,
            'amount' => $investment->amount,
        ]);
    }
    
    private function processBankTransfer($investment)
    {
        // Get bank details from configuration
        $bankConfig = config('cgo.bank_details');
        
        // Generate unique account number if not configured
        $accountNumber = $bankConfig['account_number'] ?: 'CGO-' . str_pad($investment->id, 8, '0', STR_PAD_LEFT);
        
        // Store the bank transfer reference
        $investment->update([
            'bank_transfer_reference' => 'CGO-' . $investment->uuid,
        ]);
        
        // Schedule payment verification job for bank transfers
        // Check after 1 hour, then every 6 hours
        VerifyCgoPayment::dispatch($investment)->delay(now()->addHour());
        
        return view('cgo.bank-transfer', [
            'investment' => $investment,
            'bankDetails' => [
                'bank_name' => $bankConfig['bank_name'] ?: 'Example Bank',
                'account_name' => $bankConfig['account_name'] ?: 'FinAegis CGO Investment Account',
                'account_number' => $accountNumber,
                'sort_code' => $bankConfig['sort_code'] ?: '00-00-00',
                'reference' => 'CGO-' . $investment->uuid,
            ],
        ]);
    }
    
    public function thankYou($investmentId)
    {
        $investment = CgoInvestment::where('uuid', $investmentId)->firstOrFail();
        
        return view('cgo.thank-you', [
            'investment' => $investment,
        ]);
    }
    
    private function processCardPayment($investment)
    {
        try {
            $stripeService = new StripePaymentService();
            $session = $stripeService->createCheckoutSession($investment);
            
            // Store the session ID for later verification
            $investment->update([
                'stripe_session_id' => $session->id,
            ]);
            
            return redirect($session->url);
        } catch (\Exception $e) {
            \Log::error('Error creating Stripe checkout session: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Unable to process card payment. Please try another payment method.']);
        }
    }
    
    public function paymentSuccess(Request $request, $investmentUuid)
    {
        $investment = CgoInvestment::where('uuid', $investmentUuid)->firstOrFail();
        
        // Verify the payment was successful
        if ($investment->stripe_session_id) {
            try {
                $stripeService = new StripePaymentService();
                if ($stripeService->verifyPayment($investment)) {
                    $investment->update([
                        'status' => 'confirmed',
                        'confirmed_at' => now(),
                    ]);
                    
                    // Send confirmation email
                    try {
                        Mail::to($investment->email)->send(new CgoInvestmentReceived($investment));
                    } catch (\Exception $e) {
                        \Log::error('Failed to send investment confirmation email: ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error verifying Stripe payment: ' . $e->getMessage());
            }
        }
        
        return view('cgo.payment-success', [
            'investment' => $investment,
        ]);
    }
    
    public function paymentCancel(Request $request, $investmentUuid)
    {
        $investment = CgoInvestment::where('uuid', $investmentUuid)->firstOrFail();
        
        // Update status to cancelled
        $investment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        
        return view('cgo.payment-cancel', [
            'investment' => $investment,
        ]);
    }
}