<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\Lending\LoanApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = LoanApplication::where('borrower_id', $request->user()->id)
            ->orderBy('submitted_at', 'desc')
            ->paginate(10);
            
        return response()->json($applications);
    }
    
    public function show($id)
    {
        $application = LoanApplication::where('borrower_id', auth()->id())
            ->findOrFail($id);
            
        return response()->json($application);
    }
    
    public function store(Request $request, LoanApplicationService $service)
    {
        $validated = $request->validate([
            'requested_amount' => 'required|numeric|min:1000|max:100000',
            'term_months' => 'required|integer|min:6|max:60',
            'purpose' => 'required|string|in:personal,business,debt_consolidation,education,medical,home_improvement,other',
            'employment_status' => 'required|string',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_expenses' => 'required|numeric|min:0',
            'additional_info' => 'nullable|string|max:500',
        ]);
        
        $applicationId = 'app_' . Str::uuid()->toString();
        $borrowerId = $request->user()->id;
        
        $borrowerInfo = [
            'employment_status' => $validated['employment_status'],
            'monthly_income' => $validated['monthly_income'],
            'monthly_expenses' => $validated['monthly_expenses'],
            'additional_info' => $validated['additional_info'] ?? null,
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
        
        return response()->json([
            'application' => $application,
            'result' => $result,
        ], 201);
    }
    
    public function cancel($id)
    {
        $application = LoanApplication::where('borrower_id', auth()->id())
            ->where('status', 'submitted')
            ->findOrFail($id);
            
        // In a real implementation, we would trigger a cancellation event
        $application->update([
            'status' => 'cancelled',
            'rejected_by' => 'borrower',
            'rejected_at' => now(),
            'rejection_reasons' => ['Cancelled by borrower'],
        ]);
        
        return response()->json([
            'message' => 'Application cancelled successfully',
            'application' => $application,
        ]);
    }
}