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
        
        // For regular customers, show only their fraud alerts
        if ($user->hasRole(['customer_private', 'customer_business'])) {
            $fraudCases = FraudCase::whereHas('transaction', function ($query) use ($user) {
                $query->whereHas('account', function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid);
                });
            })->latest()->paginate(10);
        } else {
            // For staff, show all fraud cases they have permission to see
            $this->authorize('view_fraud_alerts');
            $fraudCases = FraudCase::with(['transaction.account.user'])
                ->latest()
                ->paginate(20);
        }
        
        // Get fraud statistics
        $stats = [
            'total_cases' => $fraudCases->total(),
            'pending_cases' => FraudCase::where('status', 'pending')->count(),
            'confirmed_cases' => FraudCase::where('status', 'confirmed')->count(),
            'false_positives' => FraudCase::where('status', 'false_positive')->count(),
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
            if ($fraudCase->transaction->account->user_uuid !== $user->uuid) {
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