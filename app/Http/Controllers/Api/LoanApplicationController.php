<?php

namespace App\Http\Controllers\Api;

use App\Domain\Lending\Models\LoanApplication;
use App\Domain\Lending\Services\LoanApplicationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    /**
     * List loan applications.
     *
     * @OA\Get(
     *     path="/api/v1/lending/applications",
     *     operationId="loanApplicationsList",
     *     summary="List loan applications",
     *     description="Returns a paginated list of the authenticated user's loan applications ordered by submission date.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of loan applications",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", example="app_uuid"),
     *                 @OA\Property(property="borrower_id", type="string"),
     *                 @OA\Property(property="status", type="string", example="submitted"),
     *                 @OA\Property(property="requested_amount", type="number", example=10000),
     *                 @OA\Property(property="term_months", type="integer", example=12),
     *                 @OA\Property(property="purpose", type="string", example="personal"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="total", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $applications = LoanApplication::where('borrower_id', $request->user()->id)
            ->orderBy('submitted_at', 'desc')
            ->paginate(10);

        return response()->json($applications);
    }

    /**
     * Show loan application details.
     *
     * @OA\Get(
     *     path="/api/v1/lending/applications/{id}",
     *     operationId="loanApplicationsShow",
     *     summary="Show loan application",
     *     description="Returns detailed information about a specific loan application.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Loan application ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Loan application details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", example="app_uuid"),
     *             @OA\Property(property="borrower_id", type="string"),
     *             @OA\Property(property="status", type="string", example="submitted"),
     *             @OA\Property(property="requested_amount", type="number", example=10000),
     *             @OA\Property(property="term_months", type="integer", example=12),
     *             @OA\Property(property="purpose", type="string", example="personal"),
     *             @OA\Property(property="submitted_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function show($id)
    {
        $application = LoanApplication::where('borrower_id', auth()->id())
            ->findOrFail($id);

        return response()->json($application);
    }

    /**
     * Submit a new loan application.
     *
     * @OA\Post(
     *     path="/api/v1/lending/applications",
     *     operationId="loanApplicationsStore",
     *     summary="Submit loan application",
     *     description="Submits a new loan application for processing. The application undergoes credit assessment and risk scoring.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"requested_amount", "term_months", "purpose", "employment_status", "monthly_income", "monthly_expenses"},
     *             @OA\Property(property="requested_amount", type="number", format="float", minimum=1000, maximum=100000, example=15000, description="Loan amount requested"),
     *             @OA\Property(property="term_months", type="integer", minimum=6, maximum=60, example=24, description="Loan term in months"),
     *             @OA\Property(property="purpose", type="string", enum={"personal", "business", "debt_consolidation", "education", "medical", "home_improvement", "other"}, example="personal", description="Purpose of the loan"),
     *             @OA\Property(property="employment_status", type="string", example="employed", description="Borrower's employment status"),
     *             @OA\Property(property="monthly_income", type="number", format="float", minimum=0, example=5000, description="Monthly income"),
     *             @OA\Property(property="monthly_expenses", type="number", format="float", minimum=0, example=2000, description="Monthly expenses"),
     *             @OA\Property(property="additional_info", type="string", maxLength=500, example="First-time borrower", description="Optional additional information")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Application submitted",
     *         @OA\JsonContent(
     *             @OA\Property(property="application", type="object",
     *                 @OA\Property(property="id", type="string", example="app_uuid"),
     *                 @OA\Property(property="status", type="string", example="submitted"),
     *                 @OA\Property(property="requested_amount", type="number", example=15000),
     *                 @OA\Property(property="term_months", type="integer", example=24)
     *             ),
     *             @OA\Property(property="result", type="object", description="Processing result from credit assessment")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, LoanApplicationService $service)
    {
        $validated = $request->validate(
            [
                'requested_amount'  => 'required|numeric|min:1000|max:100000',
                'term_months'       => 'required|integer|min:6|max:60',
                'purpose'           => 'required|string|in:personal,business,debt_consolidation,education,medical,home_improvement,other',
                'employment_status' => 'required|string',
                'monthly_income'    => 'required|numeric|min:0',
                'monthly_expenses'  => 'required|numeric|min:0',
                'additional_info'   => 'nullable|string|max:500',
            ]
        );

        $applicationId = 'app_' . Str::uuid()->toString();
        $borrowerId = $request->user()->id;

        $borrowerInfo = [
            'employment_status' => $validated['employment_status'],
            'monthly_income'    => $validated['monthly_income'],
            'monthly_expenses'  => $validated['monthly_expenses'],
            'additional_info'   => $validated['additional_info'] ?? null,
        ];

        // Process application
        $result = $service->processApplication(
            $applicationId,
            $borrowerId,
            $validated['requested_amount'],
            $validated['term_months'],
            $validated['purpose'],
            $borrowerInfo
        );

        // Get the created application
        $application = LoanApplication::find($applicationId);

        return response()->json(
            [
                'application' => $application,
                'result'      => $result,
            ],
            201
        );
    }

    /**
     * Cancel a loan application.
     *
     * @OA\Post(
     *     path="/api/v1/lending/applications/{id}/cancel",
     *     operationId="loanApplicationsCancel",
     *     summary="Cancel loan application",
     *     description="Cancels a submitted loan application. Only applications with 'submitted' status can be cancelled.",
     *     tags={"Lending"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Loan application ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Application cancelled successfully"),
     *             @OA\Property(property="application", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="status", type="string", example="cancelled"),
     *                 @OA\Property(property="rejected_by", type="string", example="borrower"),
     *                 @OA\Property(property="rejected_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Application not found or not in submitted status")
     * )
     */
    public function cancel($id)
    {
        $application = LoanApplication::where('borrower_id', auth()->id())
            ->where('status', 'submitted')
            ->findOrFail($id);

        // In a real implementation, we would trigger a cancellation event
        $application->update(
            [
                'status'            => 'cancelled',
                'rejected_by'       => 'borrower',
                'rejected_at'       => now(),
                'rejection_reasons' => ['Cancelled by borrower'],
            ]
        );

        return response()->json(
            [
                'message'     => 'Application cancelled successfully',
                'application' => $application,
            ]
        );
    }
}
