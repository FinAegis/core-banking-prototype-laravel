<?php

namespace App\Http\Controllers;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Stablecoin\Models\StablecoinOperation;
use App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow;
use App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

class StablecoinOperationsController extends Controller
{
    /**
     * Display stablecoin operations dashboard.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        /** @var User $user */

        // Check if user has admin/operator permissions
        if (! $user->hasRole(['super_admin', 'bank_admin', 'stablecoin_operator'])) {
            abort(403, 'Unauthorized access to stablecoin operations');
        }

        // Get stablecoin assets (mock data for now)
        $stablecoins = $this->getStablecoins();

        // Get operation statistics
        $statistics = $this->getOperationStatistics();

        // Get recent operations
        $recentOperations = $this->getRecentOperations();

        // Get collateral information
        $collateral = $this->getCollateralInfo();

        // Get pending requests
        $pendingRequests = $this->getPendingRequests();

        return view(
            'stablecoin-operations.index',
            compact(
                'stablecoins',
                'statistics',
                'recentOperations',
                'collateral',
                'pendingRequests'
            )
        );
    }

    /**
     * Show mint form.
     */
    public function mint(Request $request)
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin', 'stablecoin_operator'])) {
            abort(403);
        }

        $stablecoin = $request->get('stablecoin', 'USDX');
        $stablecoinInfo = $this->getStablecoinInfo($stablecoin);

        // Get available collateral assets
        $collateralAssets = $this->getCollateralAssets();

        // Get operator accounts
        $operatorAccounts = $this->getOperatorAccounts($user);

        return view(
            'stablecoin-operations.mint',
            compact(
                'stablecoin',
                'stablecoinInfo',
                'collateralAssets',
                'operatorAccounts'
            )
        );
    }

    /**
     * Process mint request.
     */
    public function processMint(Request $request)
    {
        $validated = $request->validate(
            [
                'stablecoin'         => 'required|string|in:USDX,EURX,GBPX',
                'amount'             => 'required|numeric|min:100|max:1000000',
                'collateral_asset'   => 'required|string|in:USD,EUR,GBP',
                'collateral_amount'  => 'required|numeric|min:0',
                'recipient_account'  => 'required|uuid',
                'reason'             => 'required|string|max:255',
                'authorization_code' => 'required|string',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin', 'stablecoin_operator'])) {
            abort(403);
        }

        // Verify authorization code (mock verification)
        if (! $this->verifyAuthorizationCode($validated['authorization_code'])) {
            return back()->withErrors(['authorization_code' => 'Invalid authorization code']);
        }

        // Check collateral ratio
        $requiredCollateral = $this->calculateRequiredCollateral(
            $validated['stablecoin'],
            $validated['amount'],
            $validated['collateral_asset']
        );

        if ($validated['collateral_amount'] < $requiredCollateral) {
            return back()->withErrors(
                [
                    'collateral_amount' => "Insufficient collateral. Required: {$requiredCollateral} {$validated['collateral_asset']}",
                ]
            );
        }

        try {
            // Get recipient account
            /** @var Account|null $recipientAccount */
            $recipientAccount = Account::where('uuid', $validated['recipient_account'])->first();

            if (! $recipientAccount) {
                return back()->withErrors(['recipient_account' => 'Invalid recipient account']);
            }

            // Convert amounts to cents
            $mintAmount = (int) ($validated['amount'] * 100);
            $collateralAmount = (int) ($validated['collateral_amount'] * 100);

            // Execute mint workflow
            $workflow = WorkflowStub::make(MintStablecoinWorkflow::class);
            $positionUuid = $workflow->start(
                AccountUuid::fromString($recipientAccount->uuid),
                $validated['stablecoin'],
                $validated['collateral_asset'],
                $collateralAmount,
                $mintAmount
            );

            // Record the operation for audit
            $operationId = (string) Str::uuid();
            StablecoinOperation::create(
                [
                    'uuid'              => $operationId,
                    'type'              => 'mint',
                    'stablecoin'        => $validated['stablecoin'],
                    'amount'            => $mintAmount,
                    'collateral_asset'  => $validated['collateral_asset'],
                    'collateral_amount' => $collateralAmount,
                    'recipient_account' => $validated['recipient_account'],
                    'operator_uuid'     => $user->uuid,
                    'position_uuid'     => $positionUuid,
                    'reason'            => $validated['reason'],
                    'status'            => 'completed',
                    'metadata'          => [
                        'authorized_at' => now()->toIso8601String(),
                    ],
                    'executed_at' => now(),
                ]
            );

            return redirect()
                ->route('stablecoin-operations.index')
                ->with('success', "Successfully minted {$validated['amount']} {$validated['stablecoin']}");
        } catch (Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to process mint operation: ' . $e->getMessage()]);
        }
    }

    /**
     * Show burn form.
     */
    public function burn(Request $request)
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin', 'stablecoin_operator'])) {
            abort(403);
        }

        $stablecoin = $request->get('stablecoin', 'USDX');
        $stablecoinInfo = $this->getStablecoinInfo($stablecoin);

        // Get operator accounts with stablecoin balances
        $operatorAccounts = $this->getOperatorAccountsWithBalance($user, $stablecoin);

        return view(
            'stablecoin-operations.burn',
            compact(
                'stablecoin',
                'stablecoinInfo',
                'operatorAccounts'
            )
        );
    }

    /**
     * Process burn request.
     */
    public function processBurn(Request $request)
    {
        $validated = $request->validate(
            [
                'stablecoin'         => 'required|string|in:USDX,EURX,GBPX',
                'amount'             => 'required|numeric|min:100|max:1000000',
                'source_account'     => 'required|uuid',
                'return_collateral'  => 'required|boolean',
                'collateral_asset'   => 'required_if:return_collateral,true|string|in:USD,EUR,GBP',
                'reason'             => 'required|string|max:255',
                'authorization_code' => 'required|string',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin', 'stablecoin_operator'])) {
            abort(403);
        }

        // Verify authorization code
        if (! $this->verifyAuthorizationCode($validated['authorization_code'])) {
            return back()->withErrors(['authorization_code' => 'Invalid authorization code']);
        }

        // Check account balance
        /** @var Account|null $sourceAccount */
        $sourceAccount = Account::where('uuid', $validated['source_account'])->first();
        if (! $sourceAccount) {
            return back()->withErrors(['source_account' => 'Invalid account']);
        }

        $balance = $sourceAccount->getBalance($validated['stablecoin']);
        $amountInCents = $validated['amount'] * 100;

        if ($balance < $amountInCents) {
            return back()->withErrors(['amount' => 'Insufficient balance']);
        }

        try {
            // Calculate collateral return if applicable
            $collateralReturn = 0;
            if ($validated['return_collateral']) {
                $collateralReturn = $this->calculateCollateralReturn(
                    $validated['stablecoin'],
                    $validated['amount'],
                    $validated['collateral_asset']
                );
            }

            // For demo purposes, create a temporary position UUID
            // In production, this would be retrieved from the user's existing positions
            $positionUuid = (string) Str::uuid();

            // Execute burn workflow
            $workflow = WorkflowStub::make(BurnStablecoinWorkflow::class);
            $result = $workflow->start(
                AccountUuid::fromString($sourceAccount->uuid),
                $positionUuid,
                $validated['stablecoin'],
                $amountInCents,
                $collateralReturn * 100, // Convert to cents
                false // Don't close position
            );

            // Record the operation for audit
            $operationId = (string) Str::uuid();
            StablecoinOperation::create(
                [
                    'uuid'              => $operationId,
                    'type'              => 'burn',
                    'stablecoin'        => $validated['stablecoin'],
                    'amount'            => $amountInCents,
                    'collateral_asset'  => $validated['collateral_asset'] ?? null,
                    'collateral_return' => $collateralReturn > 0 ? (int) ($collateralReturn * 100) : null,
                    'source_account'    => $validated['source_account'],
                    'operator_uuid'     => $user->uuid,
                    'position_uuid'     => $positionUuid,
                    'reason'            => $validated['reason'],
                    'status'            => 'completed',
                    'metadata'          => [
                        'return_collateral' => $validated['return_collateral'],
                        'authorized_at'     => now()->toIso8601String(),
                    ],
                    'executed_at' => now(),
                ]
            );

            return redirect()
                ->route('stablecoin-operations.index')
                ->with('success', "Successfully burned {$validated['amount']} {$validated['stablecoin']}");
        } catch (Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to process burn operation: ' . $e->getMessage()]);
        }
    }

    /**
     * Show operation history.
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin', 'stablecoin_operator'])) {
            abort(403);
        }

        $filters = [
            'type'       => $request->get('type', 'all'),
            'stablecoin' => $request->get('stablecoin', 'all'),
            'date_from'  => $request->get('date_from'),
            'date_to'    => $request->get('date_to'),
        ];

        // Get operations from database
        $operations = $this->getOperationHistory($filters);

        // Get summary statistics
        $summary = $this->getOperationSummary($operations);

        return view(
            'stablecoin-operations.history',
            compact(
                'operations',
                'summary',
                'filters'
            )
        );
    }

    /**
     * Get stablecoins.
     */
    private function getStablecoins()
    {
        return [
            [
                'symbol'           => 'USDX',
                'name'             => 'USD Stablecoin',
                'total_supply'     => 10000000 * 100, // In cents
                'collateral_ratio' => 150, // 150%
                'active'           => true,
            ],
            [
                'symbol'           => 'EURX',
                'name'             => 'EUR Stablecoin',
                'total_supply'     => 5000000 * 100,
                'collateral_ratio' => 150,
                'active'           => true,
            ],
            [
                'symbol'           => 'GBPX',
                'name'             => 'GBP Stablecoin',
                'total_supply'     => 3000000 * 100,
                'collateral_ratio' => 150,
                'active'           => false,
            ],
        ];
    }

    /**
     * Get operation statistics.
     */
    private function getOperationStatistics()
    {
        return Cache::remember(
            'stablecoin_stats',
            300,
            function () {
                return [
                    'total_minted_24h'   => rand(50000, 200000) * 100,
                    'total_burned_24h'   => rand(30000, 150000) * 100,
                    'active_stablecoins' => 2,
                    'collateral_locked'  => rand(15000000, 25000000) * 100,
                    'operations_today'   => rand(10, 50),
                    'pending_requests'   => rand(0, 5),
                ];
            }
        );
    }

    /**
     * Get recent operations.
     */
    private function getRecentOperations()
    {
        return StablecoinOperation::with('operator')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(
                function ($operation) {
                    return [
                        'id'         => $operation->uuid,
                        'type'       => $operation->type,
                        'stablecoin' => $operation->stablecoin,
                        'amount'     => $operation->amount / 100, // Convert cents to dollars for display
                        'operator'   => $operation->operator->name ?? 'Unknown',
                        'status'     => $operation->status,
                        'created_at' => $operation->created_at,
                    ];
                }
            );
    }

    /**
     * Get collateral information.
     */
    private function getCollateralInfo()
    {
        return [
            'USD' => [
                'locked'      => 10000000 * 100,
                'available'   => 5000000 * 100,
                'utilization' => 66.67,
            ],
            'EUR' => [
                'locked'      => 5000000 * 100,
                'available'   => 3000000 * 100,
                'utilization' => 62.5,
            ],
            'GBP' => [
                'locked'      => 3000000 * 100,
                'available'   => 2000000 * 100,
                'utilization' => 60,
            ],
        ];
    }

    /**
     * Get pending requests.
     */
    private function getPendingRequests()
    {
        // Mock pending requests
        return collect([]);
    }

    /**
     * Get stablecoin info.
     */
    private function getStablecoinInfo($symbol)
    {
        $stablecoins = collect($this->getStablecoins());

        return $stablecoins->firstWhere('symbol', $symbol);
    }

    /**
     * Get collateral assets.
     */
    private function getCollateralAssets()
    {
        return [
            'USD' => ['name' => 'US Dollar', 'rate' => 1],
            'EUR' => ['name' => 'Euro', 'rate' => 0.92],
            'GBP' => ['name' => 'British Pound', 'rate' => 0.79],
        ];
    }

    /**
     * Get operator accounts.
     */
    private function getOperatorAccounts($user)
    {
        // For demo, return all user accounts
        return $user->accounts()->with(['balances.asset'])->get();
    }

    /**
     * Get operator accounts with specific stablecoin balance.
     */
    private function getOperatorAccountsWithBalance($user, $stablecoin)
    {
        return $user->accounts()
            ->with(
                ['balances' => function ($query) use ($stablecoin) {
                    $query->where('asset_code', $stablecoin)
                        ->where('balance', '>', 0);
                }]
            )
            ->get()
            ->filter(
                function ($account) {
                    return $account->balances->isNotEmpty();
                }
            );
    }

    /**
     * Verify authorization code.
     */
    private function verifyAuthorizationCode($code)
    {
        // Mock verification - in production, verify against secure system
        return strlen($code) >= 6;
    }

    /**
     * Calculate required collateral.
     */
    private function calculateRequiredCollateral($stablecoin, $amount, $collateralAsset)
    {
        $stablecoinInfo = $this->getStablecoinInfo($stablecoin);
        $collateralRatio = $stablecoinInfo['collateral_ratio'] / 100;

        // Convert based on exchange rates
        $rates = [
            'USD' => 1,
            'EUR' => 0.92,
            'GBP' => 0.79,
        ];

        $baseAmount = $amount * $collateralRatio;
        $rate = $rates[$collateralAsset] ?? 1;

        return round($baseAmount / $rate, 2);
    }

    /**
     * Calculate collateral return.
     */
    private function calculateCollateralReturn($stablecoin, $amount, $collateralAsset)
    {
        // Return 95% of collateral value (5% fee)
        return $this->calculateRequiredCollateral($stablecoin, $amount, $collateralAsset) * 0.95;
    }

    /**
     * Get operation history.
     */
    private function getOperationHistory($filters)
    {
        $query = StablecoinOperation::with(['operator', 'sourceAccount', 'recipientAccount']);

        // Apply filters
        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if ($filters['stablecoin'] !== 'all') {
            $query->where('stablecoin', $filters['stablecoin']);
        }

        if ($filters['date_from']) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(
                function ($operation) {
                    return [
                        'id'                => $operation->uuid,
                        'type'              => $operation->type,
                        'stablecoin'        => $operation->stablecoin,
                        'amount'            => $operation->amount,
                        'collateral_asset'  => $operation->collateral_asset,
                        'collateral_amount' => $operation->collateral_amount,
                        'collateral_return' => $operation->collateral_return,
                        'source_account'    => $operation->source_account,
                        'recipient_account' => $operation->recipient_account,
                        'return_collateral' => $operation->metadata['return_collateral'] ?? false,
                        'operator'          => $operation->operator->name ?? 'Unknown',
                        'reason'            => $operation->reason,
                        'position_uuid'     => $operation->position_uuid,
                        'status'            => $operation->status,
                        'created_at'        => $operation->created_at,
                    ];
                }
            );
    }

    /**
     * Get operation summary.
     */
    private function getOperationSummary($operations)
    {
        $totalMinted = $operations->where('type', 'mint')->sum('amount');
        $totalBurned = $operations->where('type', 'burn')->sum('amount');

        return [
            'total_operations'  => $operations->count(),
            'mint_operations'   => $operations->where('type', 'mint')->count(),
            'burn_operations'   => $operations->where('type', 'burn')->count(),
            'total_minted'      => $totalMinted,
            'total_burned'      => $totalBurned,
            'net_supply_change' => $totalMinted - $totalBurned,
        ];
    }
}
