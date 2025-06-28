<?php

namespace App\Http\Controllers;

use App\Models\CgoNotification;
use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\CgoNotificationReceived;
use App\Mail\CgoInvestmentReceived;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CgoController extends Controller
{
    public function notify(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);
        
        // Check if email already exists
        $existing = CgoNotification::where('email', $validated['email'])->first();
        
        if (!$existing) {
            CgoNotification::create([
                'email' => $validated['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            // Send confirmation email
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
            
            // Generate payment details based on method
            if ($validated['payment_method'] === 'crypto') {
                return $this->processCryptoPayment($investment, $validated['crypto_currency']);
            } elseif ($validated['payment_method'] === 'bank_transfer') {
                return $this->processBankTransfer($investment);
            } else {
                return $this->processCardPayment($investment);
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('CGO investment error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'An error occurred processing your investment. Please try again.']);
        }
    }
    
    private function processCryptoPayment($investment, $cryptoCurrency)
    {
        // Generate crypto address (in production, this would use a real crypto payment processor)
        $cryptoAddresses = [
            'BTC' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', // Example address
            'ETH' => '0x742d35Cc6634C0532925a3b844Bc9e7595f6C123', // Example address  
            'USDT' => '0x742d35Cc6634C0532925a3b844Bc9e7595f6C456', // Example address
            'USDC' => '0x742d35Cc6634C0532925a3b844Bc9e7595f6C789', // Example address
        ];
        
        $cryptoAddress = $cryptoAddresses[$cryptoCurrency];
        
        $investment->update([
            'crypto_address' => $cryptoAddress,
        ]);
        
        // In production, we'd monitor the blockchain for payment confirmation
        
        return view('cgo.crypto-payment', [
            'investment' => $investment,
            'cryptoCurrency' => $cryptoCurrency,
            'cryptoAddress' => $cryptoAddress,
            'amount' => $investment->amount, // In production, convert to crypto amount
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
        // In production, this would integrate with Stripe or similar
        return redirect()->route('cgo.payment', $investment->uuid);
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