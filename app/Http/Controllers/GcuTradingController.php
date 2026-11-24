<?php

namespace App\Http\Controllers;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Basket\Models\BasketValue;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'GCU Trading',
    description: 'GCU demo conversion interface (simulated; GCU has no monetary value)'
)]
class GcuTradingController extends Controller
{
        #[OA\Get(
            path: '/gcu/trading',
            operationId: 'gCUTradingIndex',
            tags: ['GCU Trading'],
            summary: 'GCU demo conversion dashboard',
            description: 'Returns the simulated GCU demo conversion interface',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();

        // The page's buy and sell forms call POST /api/v2/gcu/buy and
        // /api/v2/gcu/sell, which convert on the user's primary account.
        /** @var Account|null $account */
        $account = $user->primaryAccount();
        $gcuBalance = $account?->getBalance('GCU') ?? 0;
        $usdBalance = $account?->getBalance('USD') ?? 0;

        // Latest calculated GCU value in USD (BasketValueCalculationService).
        $currentValue = BasketValue::query()
            ->where('basket_asset_code', 'GCU')
            ->orderByDesc('calculated_at')
            ->first();

        // A conversion books a transfer_out row for the asset given and a
        // transfer_in row for the asset received (AssetTransferProjector).
        $recentConversions = TransactionProjection::query()
            ->whereIn('account_uuid', $user->accounts()->pluck('uuid'))
            ->where('asset_code', 'GCU')
            ->where('subtype', 'conversion')
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view(
            'gcu.trading',
            compact(
                'currentValue',
                'gcuBalance',
                'usdBalance',
                'recentConversions'
            )
        );
    }
}
