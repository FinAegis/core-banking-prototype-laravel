<?php

namespace App\Http\Controllers;

use App\Services\Lending\LoanApplicationService;
use App\Domain\Lending\Services\CreditScoringService;
use App\Domain\Lending\Services\RiskAssessmentService;
use App\Domain\Lending\Services\CollateralManagementService;
use App\Domain\Lending\DataObjects\LoanApplication;
use App\Domain\Lending\DataObjects\RepaymentSchedule;
use App\Domain\Lending\Projections\Loan;
use App\Domain\Lending\Projections\LoanRepayment;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LendingController extends Controller
{
    public function __construct(
        private LoanApplicationService $loanApplicationService,
        private CreditScoringService $creditScoringService,
        private RiskAssessmentService $riskAssessmentService,
        private CollateralManagementService $collateralManagementService
    ) {}

    /**
     * Display lending dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get user's loans
        $loans = Loan::where('borrower_account_uuid', function($query) use ($user) {
            $query->select('uuid')
                ->from('accounts')
                ->where('user_uuid', $user->uuid);
        })->with(['repayments', 'collaterals'])->get();
        
        // Calculate statistics
        $statistics = $this->calculateUserStatistics($loans);
        
        // Get available loan products
        $loanProducts = $this->getAvailableLoanProducts();
        
        // Get user's credit score
        $creditScore = $this->getUserCreditScore();
        
        return view('lending.index', compact('loans', 'statistics', 'loanProducts', 'creditScore'));
    }

    /**
     * Show loan application form
     */
    public function apply()
    {
        $user = Auth::user();
        $accounts = $user->accounts()->with('balances.asset')->get();
        $loanProducts = $this->getAvailableLoanProducts();
        $creditScore = $this->getUserCreditScore();
        $collateralAssets = $this->getCollateralAssets();
        
        return view('lending.apply', compact('accounts', 'loanProducts', 'creditScore', 'collateralAssets'));
    }

    /**
     * Submit loan application
     */
    public function submitApplication(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|uuid',
            'loan_product' => 'required|string',
            'amount' => 'required|numeric|min:100|max:1000000',
            'term_months' => 'required|integer|min:1|max:360',
            'purpose' => 'required|string|max:500',
            'collateral_type' => 'required|in:crypto,asset,none',
            'collateral_asset' => 'required_unless:collateral_type,none|string',
            'collateral_amount' => 'required_unless:collateral_type,none|numeric|min:0',
            'employment_status' => 'required|string',
            'annual_income' => 'required|numeric|min:0',
        ]);
        
        $account = Account::where('uuid', $validated['account_id'])
            ->where('user_uuid', Auth::user()->uuid)
            ->first();
            
        if (!$account) {
            return back()->withErrors(['account_id' => 'Invalid account']);
        }
        
        try {
            // Create loan application
            $application = new LoanApplication(
                applicationUuid: Str::uuid()->toString(),
                borrowerAccountUuid: $account->uuid,
                loanProduct: $validated['loan_product'],
                amount: $validated['amount'],
                termMonths: $validated['term_months'],
                purpose: $validated['purpose'],
                collateralType: $validated['collateral_type'],
                collateralDetails: $validated['collateral_type'] !== 'none' ? [
                    'asset' => $validated['collateral_asset'],
                    'amount' => $validated['collateral_amount'],
                ] : [],
                employmentStatus: $validated['employment_status'],
                annualIncome: $validated['annual_income'],
                metadata: []
            );
            
            // Submit application
            $result = $this->loanApplicationService->submitApplication($application);
            
            return redirect()
                ->route('lending.application', $result['application_uuid'])
                ->with('success', 'Loan application submitted successfully');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to submit application: ' . $e->getMessage()]);
        }
    }

    /**
     * Show loan application details
     */
    public function showApplication($applicationId)
    {
        $application = $this->getLoanApplication($applicationId);
        
        if (!$application || !$this->userOwnsApplication($application)) {
            abort(404, 'Application not found');
        }
        
        $creditAssessment = $this->creditScoringService->assessApplication($application);
        $riskAssessment = $this->riskAssessmentService->assessLoanRisk($application);
        
        return view('lending.application', compact('application', 'creditAssessment', 'riskAssessment'));
    }

    /**
     * Show loan details
     */
    public function showLoan($loanId)
    {
        $loan = Loan::where('loan_uuid', $loanId)->first();
        
        if (!$loan || !$this->userOwnsLoan($loan)) {
            abort(404, 'Loan not found');
        }
        
        $repaymentSchedule = $loan->repayment_schedule;
        $repayments = $loan->repayments()->orderBy('payment_date', 'desc')->get();
        $collaterals = $loan->collaterals;
        $nextPayment = $this->getNextPayment($loan);
        
        return view('lending.loan', compact('loan', 'repaymentSchedule', 'repayments', 'collaterals', 'nextPayment'));
    }

    /**
     * Show repayment form
     */
    public function repay($loanId)
    {
        $loan = Loan::where('loan_uuid', $loanId)->first();
        
        if (!$loan || !$this->userOwnsLoan($loan)) {
            abort(404, 'Loan not found');
        }
        
        $accounts = Auth::user()->accounts()->with('balances.asset')->get();
        $nextPayment = $this->getNextPayment($loan);
        $outstandingBalance = $loan->outstanding_balance;
        
        return view('lending.repay', compact('loan', 'accounts', 'nextPayment', 'outstandingBalance'));
    }

    /**
     * Process loan repayment
     */
    public function processRepayment(Request $request, $loanId)
    {
        $validated = $request->validate([
            'account_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:scheduled,partial,full',
        ]);
        
        $loan = Loan::where('loan_uuid', $loanId)->first();
        
        if (!$loan || !$this->userOwnsLoan($loan)) {
            abort(404, 'Loan not found');
        }
        
        $account = Account::where('uuid', $validated['account_id'])
            ->where('user_uuid', Auth::user()->uuid)
            ->first();
            
        if (!$account) {
            return back()->withErrors(['account_id' => 'Invalid account']);
        }
        
        try {
            $result = $this->loanApplicationService->makeRepayment(
                $loanId,
                $account->uuid,
                $validated['amount'],
                $validated['payment_type']
            );
            
            return redirect()
                ->route('lending.loan', $loanId)
                ->with('success', 'Payment processed successfully');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to process payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Calculate user lending statistics
     */
    private function calculateUserStatistics($loans)
    {
        $activeLoans = $loans->where('status', 'active')->count();
        $totalBorrowed = $loans->sum('principal_amount');
        $totalRepaid = $loans->sum('total_repaid');
        $outstandingBalance = $loans->where('status', 'active')->sum('outstanding_balance');
        
        return [
            'active_loans' => $activeLoans,
            'total_loans' => $loans->count(),
            'total_borrowed' => $totalBorrowed,
            'total_repaid' => $totalRepaid,
            'outstanding_balance' => $outstandingBalance,
            'on_time_payments' => $this->calculateOnTimePayments($loans),
        ];
    }

    /**
     * Get available loan products
     */
    private function getAvailableLoanProducts()
    {
        return [
            [
                'id' => 'personal',
                'name' => 'Personal Loan',
                'description' => 'Unsecured personal loans for any purpose',
                'min_amount' => 1000,
                'max_amount' => 50000,
                'min_term' => 6,
                'max_term' => 60,
                'interest_rate' => 8.5,
                'collateral_required' => false,
            ],
            [
                'id' => 'crypto-backed',
                'name' => 'Crypto-Backed Loan',
                'description' => 'Loans backed by cryptocurrency collateral',
                'min_amount' => 100,
                'max_amount' => 1000000,
                'min_term' => 1,
                'max_term' => 36,
                'interest_rate' => 4.5,
                'collateral_required' => true,
                'ltv_ratio' => 50, // Loan-to-value ratio
            ],
            [
                'id' => 'business',
                'name' => 'Business Loan',
                'description' => 'Loans for business expansion and operations',
                'min_amount' => 5000,
                'max_amount' => 500000,
                'min_term' => 12,
                'max_term' => 120,
                'interest_rate' => 6.5,
                'collateral_required' => false,
            ],
        ];
    }

    /**
     * Get user's credit score
     */
    private function getUserCreditScore()
    {
        $user = Auth::user();
        
        // Mock credit score calculation
        return [
            'score' => 720,
            'rating' => 'Good',
            'factors' => [
                'payment_history' => 85,
                'credit_utilization' => 75,
                'account_history' => 90,
                'credit_mix' => 70,
                'new_credit' => 80,
            ],
            'last_updated' => now()->subDays(7),
        ];
    }

    /**
     * Get available collateral assets
     */
    private function getCollateralAssets()
    {
        return [
            'BTC' => ['name' => 'Bitcoin', 'ltv' => 50],
            'ETH' => ['name' => 'Ethereum', 'ltv' => 60],
            'USDT' => ['name' => 'Tether', 'ltv' => 80],
            'USDC' => ['name' => 'USD Coin', 'ltv' => 80],
        ];
    }

    /**
     * Get loan application
     */
    private function getLoanApplication($applicationId)
    {
        // Mock loan application data
        return (object)[
            'uuid' => $applicationId,
            'borrower_account_uuid' => Auth::user()->accounts()->first()->uuid,
            'amount' => 10000,
            'term_months' => 12,
            'purpose' => 'Home improvement',
            'status' => 'pending',
            'created_at' => now()->subDays(2),
            'metadata' => [],
        ];
    }

    /**
     * Check if user owns application
     */
    private function userOwnsApplication($application)
    {
        $userAccountUuids = Auth::user()->accounts()->pluck('uuid')->toArray();
        return in_array($application->borrower_account_uuid, $userAccountUuids);
    }

    /**
     * Check if user owns loan
     */
    private function userOwnsLoan($loan)
    {
        $userAccountUuids = Auth::user()->accounts()->pluck('uuid')->toArray();
        return in_array($loan->borrower_account_uuid, $userAccountUuids);
    }

    /**
     * Get next payment for loan
     */
    private function getNextPayment($loan)
    {
        $schedule = $loan->repayment_schedule;
        $now = now();
        
        foreach ($schedule as $payment) {
            if ($payment['status'] === 'pending' && $payment['due_date'] > $now) {
                return $payment;
            }
        }
        
        return null;
    }

    /**
     * Calculate on-time payment percentage
     */
    private function calculateOnTimePayments($loans)
    {
        $totalPayments = 0;
        $onTimePayments = 0;
        
        foreach ($loans as $loan) {
            $repayments = $loan->repayments;
            $totalPayments += $repayments->count();
            $onTimePayments += $repayments->where('is_late', false)->count();
        }
        
        return $totalPayments > 0 ? round(($onTimePayments / $totalPayments) * 100, 2) : 100;
    }
}