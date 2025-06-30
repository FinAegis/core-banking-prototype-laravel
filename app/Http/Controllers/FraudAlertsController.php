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
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Base query
        if (!$user->can('view_fraud_alerts')) {
            // For regular customers, show only their fraud alerts
            $query = FraudCase::whereHas('subjectAccount', function ($q) use ($user) {
                $q->where('user_uuid', $user->uuid);
            });
        } else {
            // For staff with permission, show fraud cases
            // The BelongsToTeam trait will automatically filter by current team
            $query = FraudCase::with(['subjectAccount.user']);
            
            // Super admins can see all teams' data
            if ($user->hasRole('super_admin')) {
                $query->allTeams();
            }
        }
        
        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        
        if ($request->filled('risk_score_min')) {
            $query->where('risk_score', '>=', $request->risk_score_min);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('detected_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('detected_at', '<=', $request->date_to);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Sort
        $sortField = $request->get('sort', 'detected_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        $fraudCases = $query->paginate(20)->withQueryString();
        
        // Get fraud statistics (respecting team boundaries)
        $statsQuery = FraudCase::query();
        if ($user->hasRole('super_admin')) {
            $statsQuery->allTeams();
        }
        
        $stats = [
            'total_cases' => (clone $statsQuery)->count(),
            'pending_cases' => (clone $statsQuery)->where('status', 'pending')->count(),
            'confirmed_cases' => (clone $statsQuery)->where('status', 'confirmed')->count(),
            'false_positives' => (clone $statsQuery)->where('status', 'false_positive')->count(),
            'investigating_cases' => (clone $statsQuery)->where('status', 'investigating')->count(),
            'resolved_cases' => (clone $statsQuery)->where('status', 'resolved')->count(),
        ];
        
        // Get trend data for the last 30 days
        $trendData = $this->getFraudTrendData($statsQuery);
        
        // Get risk distribution
        $riskDistribution = $this->getRiskDistribution($statsQuery);
        
        // Get type distribution
        $typeDistribution = $this->getTypeDistribution($statsQuery);
        
        return view('fraud.alerts.index', compact(
            'fraudCases', 
            'stats', 
            'trendData', 
            'riskDistribution',
            'typeDistribution'
        ));
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
    
    /**
     * Export fraud cases to CSV
     */
    public function export(Request $request)
    {
        $this->authorize('export_fraud_data');
        
        $filename = 'fraud-cases-' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($request) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'Case Number',
                'Type',
                'Status',
                'Severity',
                'Risk Score',
                'Amount',
                'Currency',
                'Detected At',
                'Resolved At',
                'Description'
            ]);
            
            // Apply same filters as index
            $user = Auth::user();
            $query = FraudCase::query();
            
            if (!$user->can('view_fraud_alerts')) {
                $query->whereHas('subjectAccount', function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid);
                });
            } elseif ($user->hasRole('super_admin')) {
                $query->allTeams();
            }
            
            // Apply filters from request
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            
            $query->orderBy('detected_at', 'desc')
                  ->chunk(100, function ($fraudCases) use ($file) {
                      foreach ($fraudCases as $case) {
                          fputcsv($file, [
                              $case->case_number,
                              $case->type,
                              $case->status,
                              $case->severity,
                              $case->risk_score,
                              $case->amount,
                              $case->currency ?? 'USD',
                              $case->detected_at->format('Y-m-d H:i:s'),
                              $case->resolved_at?->format('Y-m-d H:i:s') ?? '',
                              $case->description ?? ''
                          ]);
                      }
                  });
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Get fraud trend data for the last 30 days
     */
    private function getFraudTrendData($baseQuery)
    {
        $endDate = now();
        $startDate = now()->subDays(29);
        
        $trendData = [];
        
        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $count = (clone $baseQuery)
                ->whereDate('detected_at', $date->format('Y-m-d'))
                ->count();
                
            $trendData[] = [
                'date' => $date->format('M d'),
                'count' => $count
            ];
        }
        
        return $trendData;
    }
    
    /**
     * Get risk score distribution
     */
    private function getRiskDistribution($baseQuery)
    {
        return [
            'low' => (clone $baseQuery)->whereBetween('risk_score', [0, 30])->count(),
            'medium' => (clone $baseQuery)->whereBetween('risk_score', [31, 60])->count(),
            'high' => (clone $baseQuery)->whereBetween('risk_score', [61, 80])->count(),
            'critical' => (clone $baseQuery)->where('risk_score', '>', 80)->count(),
        ];
    }
    
    /**
     * Get fraud type distribution
     */
    private function getTypeDistribution($baseQuery)
    {
        $types = FraudCase::FRAUD_TYPES;
        $distribution = [];
        
        foreach (array_keys($types) as $type) {
            $distribution[$type] = (clone $baseQuery)->where('type', $type)->count();
        }
        
        return $distribution;
    }
}