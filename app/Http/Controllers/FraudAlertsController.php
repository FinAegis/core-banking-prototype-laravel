<?php

namespace App\Http\Controllers;

use App\Models\FraudCase;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FraudAlertsController extends Controller
{
    /**
     * Display fraud alerts dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        // Check if user has permission to view fraud alerts
        if (!$user->can('view_fraud_alerts')) {
            // For regular customers, show only their fraud alerts
            $fraudCases = FraudCase::whereHas('subjectAccount', function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })->latest()->paginate(10);
        } else {
            // For staff with permission, show fraud cases
            // The BelongsToTeam trait will automatically filter by current team
            $query = FraudCase::with(['subjectAccount.user']);
            
            // Super admins can see all teams' data
            if ($user->hasRole('super_admin')) {
                $query->allTeams();
            }
            
            $fraudCases = $query->latest()->paginate(20);
        }
        
        // Get fraud statistics (respecting team boundaries)
        $statsQuery = FraudCase::query();
        if ($user->hasRole('super_admin')) {
            $statsQuery->allTeams();
        }
        
        $stats = [
            'total_cases' => $fraudCases->total(),
            'pending_cases' => (clone $statsQuery)->where('status', 'pending')->count(),
            'confirmed_cases' => (clone $statsQuery)->where('status', 'confirmed')->count(),
            'false_positives' => (clone $statsQuery)->where('status', 'false_positive')->count(),
        ];
        
        return view('fraud.alerts.index', compact('fraudCases', 'stats'));
    }
    
    /**
     * Show fraud case details
     */
    public function show(FraudCase $fraudCase)
    {
        $user = Auth::user();
        
        // Check authorization
        if ($user->hasRole(['customer_private', 'customer_business'])) {
            // Customers can only view their own fraud cases
            if ($fraudCase->subjectAccount && $fraudCase->subjectAccount->user_uuid !== $user->uuid) {
                abort(403);
            }
        } else {
            $this->authorize('view_fraud_alerts');
        }
        
        return view('fraud.alerts.show', compact('fraudCase'));
    }
    
    /**
     * Update fraud case status
     */
    public function updateStatus(Request $request, FraudCase $fraudCase)
    {
        $this->authorize('manage_fraud_cases');
        
        $request->validate([
            'status' => 'required|in:pending,investigating,confirmed,false_positive,resolved',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        $fraudCase->update([
            'status' => $request->status,
            'investigator_notes' => $request->notes,
            'investigated_by' => Auth::id(),
            'investigated_at' => now(),
        ]);
        
        return redirect()->route('fraud.alerts.show', $fraudCase)
            ->with('success', 'Fraud case status updated successfully.');
    }
}