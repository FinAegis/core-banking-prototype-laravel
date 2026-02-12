<?php

namespace App\Http\Controllers\Api;

use App\Domain\Lending\Aggregates\Loan as LoanAggregate;
use App\Domain\Lending\Models\Loan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    /**
     * List user loans.
     *
     * @OA\Get(
     *     path="/api/v1/lending/loans",
     *     operationId="loansList",
     *     summary="List loans",
     *     description="Returns a paginated list of the authenticated user's loans with their applications and repayments.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of loans",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", example="loan_uuid"),
     *                 @OA\Property(property="borrower_id", type="string"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="application", type="object"),
     *                 @OA\Property(property="repayments", type="array", @OA\Items(type="object"))
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="total", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $loans = Loan::where('borrower_id', $request->user()->id)
            ->with(['application', 'repayments'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($loans);
    }

    /**
     * Show loan details.
     *
     * @OA\Get(
     *     path="/api/v1/lending/loans/{id}",
     *     operationId="loansShow",
     *     summary="Show loan details",
     *     description="Returns detailed information about a specific loan including next payment and outstanding balance.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Loan ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Loan details",
     *         @OA\JsonContent(
     *             @OA\Property(property="loan", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="borrower_id", type="string"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="application", type="object"),
     *                 @OA\Property(property="repayments", type="array", @OA\Items(type="object"))
     *             ),
     *             @OA\Property(property="next_payment", type="object"),
     *             @OA\Property(property="outstanding_balance", type="string", example="5000.00")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Loan not found")
     * )
     */
    public function show($id)
    {
        $loan = Loan::where('borrower_id', auth()->id())
            ->with(['application', 'repayments'])
            ->findOrFail($id);

        return response()->json(
            [
                'loan'                => $loan,
                'next_payment'        => $loan->next_payment,
                'outstanding_balance' => $loan->outstanding_balance,
            ]
        );
    }

    /**
     * Make a loan payment.
     *
     * @OA\Post(
     *     path="/api/v1/lending/loans/{id}/payments",
     *     operationId="loansMakePayment",
     *     summary="Make a loan payment",
     *     description="Records a repayment against a scheduled payment for an active loan.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Loan ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "payment_number"},
     *             @OA\Property(property="amount", type="number", format="float", minimum=0.01, example=250.00, description="Payment amount"),
     *             @OA\Property(property="payment_number", type="integer", minimum=1, example=1, description="Scheduled payment number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment recorded",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Payment recorded successfully"),
     *             @OA\Property(property="loan", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid payment number"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Loan not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function makePayment(Request $request, $id)
    {
        $validated = $request->validate(
            [
                'amount'         => 'required|numeric|min:0.01',
                'payment_number' => 'required|integer|min:1',
            ]
        );

        $loan = Loan::where('borrower_id', auth()->id())
            ->where('status', 'active')
            ->findOrFail($id);

        // Verify payment matches schedule
        $scheduledPayment = collect($loan->repayment_schedule)
            ->firstWhere('payment_number', $validated['payment_number']);

        if (! $scheduledPayment) {
            return response()->json(
                [
                    'error' => 'Invalid payment number',
                ],
                400
            );
        }

        // Process payment through aggregate
        DB::transaction(
            function () use ($loan, $validated, $scheduledPayment) {
                $aggregate = LoanAggregate::retrieve($loan->id);
                $aggregate->recordRepayment(
                    $validated['payment_number'],
                    $validated['amount'],
                    $scheduledPayment['principal'],
                    $scheduledPayment['interest'],
                    auth()->id()
                );
                $aggregate->persist();
            }
        );

        return response()->json(
            [
                'message' => 'Payment recorded successfully',
                'loan'    => $loan->fresh(),
            ]
        );
    }

    /**
     * Get early settlement quote.
     *
     * @OA\Get(
     *     path="/api/v1/lending/loans/{id}/settlement-quote",
     *     operationId="loansSettlementQuote",
     *     summary="Get early settlement quote",
     *     description="Calculates the early settlement amount for a loan, showing outstanding balance, settlement amount, and potential savings.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Loan ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settlement quote",
     *         @OA\JsonContent(
     *             @OA\Property(property="loan_id", type="string", example="loan_uuid"),
     *             @OA\Property(property="outstanding_balance", type="string", example="5000.00"),
     *             @OA\Property(property="settlement_amount", type="string", example="4800.00"),
     *             @OA\Property(property="savings", type="string", example="200.00"),
     *             @OA\Property(property="confirm_url", type="string", example="/api/v1/lending/loans/loan_uuid/settle")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Loan not found")
     * )
     */
    public function settleEarly(Request $request, $id)
    {
        $loan = Loan::where('borrower_id', auth()->id())
            ->whereIn('status', ['active', 'delinquent'])
            ->findOrFail($id);

        $outstandingBalance = $loan->outstanding_balance;

        // Calculate early settlement amount (might include penalties or discounts)
        $settlementAmount = $this->calculateEarlySettlementAmount($loan);

        return response()->json(
            [
                'loan_id'             => $loan->id,
                'outstanding_balance' => $outstandingBalance,
                'settlement_amount'   => $settlementAmount,
                'savings'             => bcsub($outstandingBalance, $settlementAmount, 2),
                'confirm_url'         => route('api.loans.confirm-settlement', $loan->id),
            ]
        );
    }

    /**
     * Confirm early settlement.
     *
     * @OA\Post(
     *     path="/api/v1/lending/loans/{id}/settle",
     *     operationId="loansConfirmSettlement",
     *     summary="Confirm early settlement",
     *     description="Confirms and processes the early settlement of a loan. The loan must be in active or delinquent status.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Loan ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Loan settled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Loan settled successfully"),
     *             @OA\Property(property="loan", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Loan not found")
     * )
     */
    public function confirmSettlement(Request $request, $id)
    {
        $loan = Loan::where('borrower_id', auth()->id())
            ->whereIn('status', ['active', 'delinquent'])
            ->findOrFail($id);

        $settlementAmount = $this->calculateEarlySettlementAmount($loan);

        DB::transaction(
            function () use ($loan, $settlementAmount) {
                $aggregate = LoanAggregate::retrieve($loan->id);
                $aggregate->settleEarly(
                    $settlementAmount,
                    auth()->id()
                );
                $aggregate->persist();
            }
        );

        return response()->json(
            [
                'message' => 'Loan settled successfully',
                'loan'    => $loan->fresh(),
            ]
        );
    }

    private function calculateEarlySettlementAmount(Loan $loan): string
    {
        // Simple calculation - just the outstanding principal
        // In production, this might include early settlement fees or discounts
        return $loan->outstanding_balance;
    }
}
