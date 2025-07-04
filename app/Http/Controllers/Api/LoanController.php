<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Domain\Lending\Aggregates\Loan as LoanAggregate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $loans = Loan::where('borrower_id', $request->user()->id)
            ->with(['application', 'repayments'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return response()->json($loans);
    }
    
    public function show($id)
    {
        $loan = Loan::where('borrower_id', auth()->id())
            ->with(['application', 'repayments'])
            ->findOrFail($id);
            
        return response()->json([
            'loan' => $loan,
            'next_payment' => $loan->next_payment,
            'outstanding_balance' => $loan->outstanding_balance,
        ]);
    }
    
    public function makePayment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_number' => 'required|integer|min:1',
        ]);
        
        $loan = Loan::where('borrower_id', auth()->id())
            ->where('status', 'active')
            ->findOrFail($id);
            
        // Verify payment matches schedule
        $scheduledPayment = collect($loan->repayment_schedule)
            ->firstWhere('payment_number', $validated['payment_number']);
            
        if (!$scheduledPayment) {
            return response()->json([
                'error' => 'Invalid payment number',
            ], 400);
        }
        
        // Process payment through aggregate
        DB::transaction(function () use ($loan, $validated, $scheduledPayment) {
            $aggregate = LoanAggregate::retrieve($loan->id);
            $aggregate->recordRepayment(
                $validated['payment_number'],
                $validated['amount'],
                $scheduledPayment['principal'],
                $scheduledPayment['interest'],
                auth()->id()
            );
            $aggregate->persist();
        });
        
        return response()->json([
            'message' => 'Payment recorded successfully',
            'loan' => $loan->fresh(),
        ]);
    }
    
    public function settleEarly(Request $request, $id)
    {
        $loan = Loan::where('borrower_id', auth()->id())
            ->whereIn('status', ['active', 'delinquent'])
            ->findOrFail($id);
            
        $outstandingBalance = $loan->outstanding_balance;
        
        // Calculate early settlement amount (might include penalties or discounts)
        $settlementAmount = $this->calculateEarlySettlementAmount($loan);
        
        return response()->json([
            'loan_id' => $loan->id,
            'outstanding_balance' => $outstandingBalance,
            'settlement_amount' => $settlementAmount,
            'savings' => bcsub($outstandingBalance, $settlementAmount, 2),
            'confirm_url' => route('api.loans.confirm-settlement', $loan->id),
        ]);
    }
    
    public function confirmSettlement(Request $request, $id)
    {
        $loan = Loan::where('borrower_id', auth()->id())
            ->whereIn('status', ['active', 'delinquent'])
            ->findOrFail($id);
            
        $settlementAmount = $this->calculateEarlySettlementAmount($loan);
        
        DB::transaction(function () use ($loan, $settlementAmount) {
            $aggregate = LoanAggregate::retrieve($loan->id);
            $aggregate->settleEarly(
                $settlementAmount,
                auth()->id()
            );
            $aggregate->persist();
        });
        
        return response()->json([
            'message' => 'Loan settled successfully',
            'loan' => $loan->fresh(),
        ]);
    }
    
    private function calculateEarlySettlementAmount(Loan $loan): string
    {
        // Simple calculation - just the outstanding principal
        // In production, this might include early settlement fees or discounts
        return $loan->outstanding_balance;
    }
}