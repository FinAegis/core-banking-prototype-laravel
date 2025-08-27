<?php

namespace App\Http\Controllers;

use App\Domain\Cgo\Models\CgoInvestment;
use App\Domain\Cgo\Services\PaymentVerificationService;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Mail;

class CgoPaymentVerificationController extends Controller
{
    protected PaymentVerificationService $verificationService;

    public function __construct(PaymentVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Display payment verification dashboard for investors.
     */
    public function index()
    {
        $investments = CgoInvestment::where('user_id', Auth::id())
            ->whereIn('payment_status', ['pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('cgo.payment-verification', compact('investments'));
    }

    /**
     * Check payment status for a specific investment.
     */
    public function checkStatus(CgoInvestment $investment)
    {
        // Ensure user owns this investment
        if ($investment->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $updated = false;

            switch ($investment->payment_method) {
                case 'stripe':
                    if ($investment->stripe_payment_intent_id) {
                        $result = $this->verificationService->verifyStripePayment($investment);
                        $updated = $result['verified'];
                    }
                    break;

                case 'crypto':
                    if ($investment->coinbase_charge_id) {
                        $result = $this->verificationService->verifyCoinbasePayment($investment);
                        $updated = $result['verified'];
                    }
                    break;
            }

            if ($updated) {
                return response()->json(
                    [
                        'success'  => true,
                        'message'  => 'Payment has been verified!',
                        'status'   => $investment->fresh()->payment_status,
                        'redirect' => route('cgo.investments'),
                    ]
                );
            }

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Payment is still pending',
                    'status'  => $investment->payment_status,
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unable to verify payment at this time',
                    'error'   => config('app.debug') ? $e->getMessage() : null,
                ],
                500
            );
        }
    }

    /**
     * Resend payment instructions.
     */
    public function resendInstructions(CgoInvestment $investment)
    {
        // Ensure user owns this investment
        if ($investment->user_id !== Auth::id()) {
            abort(403);
        }

        if ($investment->payment_status === 'completed') {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Payment has already been completed',
                ],
                400
            );
        }

        try {
            // Send email with payment instructions based on payment method
            $user = Auth::user();
            /** @var User $user */
            switch ($investment->payment_method) {
                case 'bank_transfer':
                    // Send bank transfer instructions
                    Mail::to($user->email)->send(new \App\Mail\CgoBankTransferInstructions($investment));
                    break;

                case 'crypto':
                    // Send crypto payment instructions
                    Mail::to($user->email)->send(new \App\Mail\CgoCryptoPaymentInstructions($investment));
                    break;
            }

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Payment instructions have been sent to your email',
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unable to send instructions at this time',
                ],
                500
            );
        }
    }

    /**
     * Get payment timeline for an investment.
     */
    public function timeline(CgoInvestment $investment)
    {
        // Ensure user owns this investment
        if ($investment->user_id !== Auth::id()) {
            abort(403);
        }

        $timeline = [];

        // Investment created
        $timeline[] = [
            'date'        => $investment->created_at,
            'event'       => 'Investment initiated',
            'description' => 'Investment of $' . number_format($investment->amount / 100, 2) . ' created',
            'icon'        => 'heroicon-o-currency-dollar',
            'color'       => 'gray',
        ];

        // Payment method selected
        $timeline[] = [
            'date'        => $investment->created_at,
            'event'       => 'Payment method selected',
            'description' => ucfirst(str_replace('_', ' ', $investment->payment_method)),
            'icon'        => 'heroicon-o-credit-card',
            'color'       => 'blue',
        ];

        // Payment pending
        if ($investment->payment_pending_at) {
            $timeline[] = [
                'date'        => $investment->payment_pending_at,
                'event'       => 'Payment pending',
                'description' => 'Waiting for payment confirmation',
                'icon'        => 'heroicon-o-clock',
                'color'       => 'yellow',
            ];
        }

        // KYC verification
        if ($investment->kyc_verified_at) {
            $timeline[] = [
                'date'        => $investment->kyc_verified_at,
                'event'       => 'KYC verified',
                'description' => ucfirst($investment->kyc_level) . ' KYC completed',
                'icon'        => 'heroicon-o-shield-check',
                'color'       => 'green',
            ];
        }

        // Payment completed
        if ($investment->payment_completed_at) {
            $timeline[] = [
                'date'        => $investment->payment_completed_at,
                'event'       => 'Payment confirmed',
                'description' => 'Payment successfully processed',
                'icon'        => 'heroicon-o-check-circle',
                'color'       => 'green',
            ];
        }

        // Certificate issued
        if ($investment->certificate_issued_at) {
            $timeline[] = [
                'date'        => $investment->certificate_issued_at,
                'event'       => 'Certificate issued',
                'description' => 'Investment certificate #' . $investment->certificate_number . ' generated',
                'icon'        => 'heroicon-o-document-text',
                'color'       => 'green',
            ];
        }

        // Sort by date
        usort(
            $timeline,
            function ($a, $b) {
                return $a['date']->timestamp - $b['date']->timestamp;
            }
        );

        return response()->json($timeline);
    }
}
