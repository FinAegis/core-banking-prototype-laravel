<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BasketAsset;
use App\Models\BasketValue;
use App\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="GCU",
 *     description="Global Currency Unit specific endpoints"
 * )
 */
class GCUController extends Controller
{
    /**
     * @OA\Get(
     *     path="/gcu",
     *     operationId="getGCUInfo",
     *     tags={"GCU"},
     *     summary="Get GCU information",
     *     description="Get current information about the Global Currency Unit including composition and value",
     *     @OA\Response(
     *         response=200,
     *         description="GCU information",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="code", type="string", example="GCU"),
     *                 @OA\Property(property="name", type="string", example="Global Currency Unit"),
     *                 @OA\Property(property="symbol", type="string", example="Ǥ"),
     *                 @OA\Property(property="current_value", type="number", format="float", example=1.0975),
     *                 @OA\Property(property="value_currency", type="string", example="USD"),
     *                 @OA\Property(property="last_rebalanced", type="string", format="date-time"),
     *                 @OA\Property(property="next_rebalance", type="string", format="date-time"),
     *                 @OA\Property(property="composition", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="asset_code", type="string"),
     *                         @OA\Property(property="asset_name", type="string"),
     *                         @OA\Property(property="weight", type="number", format="float"),
     *                         @OA\Property(property="value_contribution", type="number", format="float")
     *                     )
     *                 ),
     *                 @OA\Property(property="statistics", type="object",
     *                     @OA\Property(property="total_supply", type="integer"),
     *                     @OA\Property(property="holders_count", type="integer"),
     *                     @OA\Property(property="24h_change", type="number", format="float"),
     *                     @OA\Property(property="7d_change", type="number", format="float"),
     *                     @OA\Property(property="30d_change", type="number", format="float")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $gcu = BasketAsset::where('code', 'GCU')->with('components.asset')->firstOrFail();
        $latestValue = BasketValue::where('basket_code', 'GCU')
            ->orderBy('calculated_at', 'desc')
            ->first();

        // Calculate statistics
        $statistics = $this->calculateGCUStatistics($gcu, $latestValue);

        // Get composition with current values
        $composition = $gcu->components->map(function ($component) use ($latestValue) {
            $valueContribution = 0;
            if ($latestValue && isset($latestValue->component_values[$component->asset_code])) {
                $valueContribution = $latestValue->component_values[$component->asset_code]['weighted_value'] ?? 0;
            }

            return [
                'asset_code' => $component->asset_code,
                'asset_name' => $component->asset->name,
                'weight' => $component->weight,
                'value_contribution' => round($valueContribution, 4),
            ];
        });

        return response()->json([
            'data' => [
                'code' => $gcu->code,
                'name' => $gcu->name,
                'symbol' => 'Ǥ',
                'current_value' => $latestValue ? $latestValue->value : 1.0,
                'value_currency' => 'USD',
                'last_rebalanced' => $gcu->last_rebalanced_at?->toIso8601String(),
                'next_rebalance' => $this->getNextRebalanceDate($gcu),
                'composition' => $composition,
                'statistics' => $statistics,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/gcu/value-history",
     *     operationId="getGCUValueHistory",
     *     tags={"GCU"},
     *     summary="Get GCU value history",
     *     description="Get historical value data for the Global Currency Unit",
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period for history",
     *         @OA\Schema(type="string", enum={"24h", "7d", "30d", "90d", "1y", "all"}, default="30d")
     *     ),
     *     @OA\Parameter(
     *         name="interval",
     *         in="query",
     *         required=false,
     *         description="Data interval",
     *         @OA\Schema(type="string", enum={"hourly", "daily", "weekly", "monthly"}, default="daily")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="GCU value history",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="timestamp", type="string", format="date-time"),
     *                     @OA\Property(property="value", type="number", format="float"),
     *                     @OA\Property(property="change", type="number", format="float")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="period", type="string"),
     *                 @OA\Property(property="interval", type="string"),
     *                 @OA\Property(property="start_value", type="number", format="float"),
     *                 @OA\Property(property="end_value", type="number", format="float"),
     *                 @OA\Property(property="total_change", type="number", format="float"),
     *                 @OA\Property(property="total_change_percent", type="number", format="float")
     *             )
     *         )
     *     )
     * )
     */
    public function valueHistory(Request $request): JsonResponse
    {
        $period = $request->input('period', '30d');
        $interval = $request->input('interval', 'daily');

        $startDate = $this->getPeriodStartDate($period);
        
        $values = BasketValue::where('basket_code', 'GCU')
            ->where('calculated_at', '>=', $startDate)
            ->orderBy('calculated_at')
            ->get();

        // Group by interval
        $groupedValues = $this->groupValuesByInterval($values, $interval);

        // Calculate changes
        $history = [];
        $previousValue = null;
        
        foreach ($groupedValues as $timestamp => $value) {
            $change = $previousValue ? ($value - $previousValue) / $previousValue * 100 : 0;
            
            $history[] = [
                'timestamp' => $timestamp,
                'value' => round($value, 4),
                'change' => round($change, 2),
            ];
            
            $previousValue = $value;
        }

        $startValue = $history[0]['value'] ?? 1.0;
        $endValue = end($history)['value'] ?? 1.0;
        $totalChange = $endValue - $startValue;
        $totalChangePercent = $startValue > 0 ? ($totalChange / $startValue * 100) : 0;

        return response()->json([
            'data' => $history,
            'meta' => [
                'period' => $period,
                'interval' => $interval,
                'start_value' => $startValue,
                'end_value' => $endValue,
                'total_change' => round($totalChange, 4),
                'total_change_percent' => round($totalChangePercent, 2),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/gcu/governance/active-polls",
     *     operationId="getGCUActivePolls",
     *     tags={"GCU"},
     *     summary="Get active GCU governance polls",
     *     description="Get currently active polls related to GCU governance",
     *     @OA\Response(
     *         response=200,
     *         description="Active GCU polls",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="start_date", type="string", format="date-time"),
     *                     @OA\Property(property="end_date", type="string", format="date-time"),
     *                     @OA\Property(property="participation_rate", type="number", format="float"),
     *                     @OA\Property(property="current_results", type="object"),
     *                     @OA\Property(property="time_remaining", type="object",
     *                         @OA\Property(property="days", type="integer"),
     *                         @OA\Property(property="hours", type="integer"),
     *                         @OA\Property(property="human_readable", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function activePolls(): JsonResponse
    {
        $polls = Poll::active()
            ->where('metadata->is_gcu_poll', true)
            ->with(['votes'])
            ->get();

        $data = $polls->map(function ($poll) {
            $totalVotes = $poll->votes->count();
            $totalVotingPower = $poll->votes->sum('voting_power');
            $endDate = $poll->end_date;
            $now = now();

            return [
                'id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description,
                'type' => $poll->type,
                'start_date' => $poll->start_date->toIso8601String(),
                'end_date' => $endDate->toIso8601String(),
                'participation_rate' => $poll->getParticipationRate(),
                'current_results' => $poll->getResults(),
                'time_remaining' => [
                    'days' => $now->diffInDays($endDate),
                    'hours' => $now->diffInHours($endDate) % 24,
                    'human_readable' => $now->diffForHumans($endDate),
                ],
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/gcu/supported-banks",
     *     operationId="getGCUSupportedBanks",
     *     tags={"GCU"},
     *     summary="Get supported banks for GCU",
     *     description="Get list of banks that support GCU deposits and their coverage",
     *     @OA\Response(
     *         response=200,
     *         description="Supported banks",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="code", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="deposit_protection", type="string"),
     *                     @OA\Property(property="deposit_protection_amount", type="integer"),
     *                     @OA\Property(property="supported_currencies", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="features", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="status", type="string", enum={"operational", "degraded", "maintenance"})
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function supportedBanks(): JsonResponse
    {
        $banks = [
            [
                'code' => 'paysera',
                'name' => 'Paysera LT',
                'country' => 'Lithuania',
                'deposit_protection' => 'EU Deposit Guarantee Scheme',
                'deposit_protection_amount' => 100000,
                'deposit_protection_currency' => 'EUR',
                'supported_currencies' => ['EUR', 'USD', 'GBP', 'CHF'],
                'features' => ['instant_transfers', 'multi_currency', 'api_access'],
                'status' => $this->getBankStatus('paysera'),
            ],
            [
                'code' => 'deutsche_bank',
                'name' => 'Deutsche Bank',
                'country' => 'Germany',
                'deposit_protection' => 'German Deposit Protection Scheme',
                'deposit_protection_amount' => 100000,
                'deposit_protection_currency' => 'EUR',
                'supported_currencies' => ['EUR', 'USD', 'GBP', 'CHF', 'JPY'],
                'features' => ['corporate_banking', 'fx_trading', 'global_network'],
                'status' => $this->getBankStatus('deutsche_bank'),
            ],
            [
                'code' => 'santander',
                'name' => 'Santander',
                'country' => 'Spain',
                'deposit_protection' => 'Spanish Deposit Guarantee Fund',
                'deposit_protection_amount' => 100000,
                'deposit_protection_currency' => 'EUR',
                'supported_currencies' => ['EUR', 'USD', 'GBP'],
                'features' => ['retail_banking', 'mobile_app', 'international_presence'],
                'status' => $this->getBankStatus('santander'),
            ],
        ];

        return response()->json(['data' => $banks]);
    }

    private function calculateGCUStatistics(BasketAsset $gcu, ?BasketValue $latestValue): array
    {
        // In production, these would be calculated from real data
        $totalSupply = \App\Models\AccountBalance::where('asset_code', 'GCU')
            ->sum('balance');
        
        $holdersCount = \App\Models\AccountBalance::where('asset_code', 'GCU')
            ->where('balance', '>', 0)
            ->count();

        // Calculate value changes
        $changes = $this->calculateValueChanges('GCU');

        return [
            'total_supply' => $totalSupply,
            'holders_count' => $holdersCount,
            '24h_change' => $changes['24h'],
            '7d_change' => $changes['7d'],
            '30d_change' => $changes['30d'],
        ];
    }

    private function calculateValueChanges(string $basketCode): array
    {
        $now = now();
        $currentValue = BasketValue::where('basket_code', $basketCode)
            ->orderBy('calculated_at', 'desc')
            ->value('value') ?? 1.0;

        $changes = [];
        
        foreach (['24h' => 1, '7d' => 7, '30d' => 30] as $period => $days) {
            $previousValue = BasketValue::where('basket_code', $basketCode)
                ->where('calculated_at', '<=', $now->copy()->subDays($days))
                ->orderBy('calculated_at', 'desc')
                ->value('value') ?? $currentValue;
            
            $change = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue * 100) : 0;
            $changes[$period] = round($change, 2);
        }

        return $changes;
    }

    private function getNextRebalanceDate(BasketAsset $basket): string
    {
        if ($basket->rebalance_frequency === 'never') {
            return 'Not scheduled';
        }

        $lastRebalanced = $basket->last_rebalanced_at ?? now();
        
        $nextRebalance = match ($basket->rebalance_frequency) {
            'daily' => $lastRebalanced->copy()->addDay(),
            'weekly' => $lastRebalanced->copy()->addWeek(),
            'monthly' => $lastRebalanced->copy()->addMonth(),
            'quarterly' => $lastRebalanced->copy()->addQuarter(),
            default => $lastRebalanced->copy()->addMonth(),
        };
        
        return $nextRebalance->toIso8601String();
    }

    private function getPeriodStartDate(string $period): \Carbon\Carbon
    {
        return match ($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subYears(10),
        };
    }

    private function groupValuesByInterval($values, string $interval): array
    {
        $grouped = [];
        
        foreach ($values as $value) {
            $key = match ($interval) {
                'hourly' => $value->calculated_at->format('Y-m-d H:00:00'),
                'daily' => $value->calculated_at->format('Y-m-d'),
                'weekly' => $value->calculated_at->startOfWeek()->format('Y-m-d'),
                'monthly' => $value->calculated_at->format('Y-m'),
                default => $value->calculated_at->format('Y-m-d'),
            };
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            
            $grouped[$key][] = $value->value;
        }

        // Average values for each group
        $result = [];
        foreach ($grouped as $key => $values) {
            $result[$key] = array_sum($values) / count($values);
        }

        return $result;
    }

    private function getBankStatus(string $bankCode): string
    {
        try {
            $healthMonitor = app(\App\Domain\Custodian\Services\CustodianHealthMonitor::class);
            $health = $healthMonitor->getCustodianHealth($bankCode);
            
            return match ($health['status']) {
                'healthy' => 'operational',
                'degraded' => 'degraded',
                'unhealthy' => 'maintenance',
                default => 'unknown',
            };
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}