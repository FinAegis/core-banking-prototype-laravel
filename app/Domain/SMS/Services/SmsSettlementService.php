<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use App\Domain\SMS\Models\SmsMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Settlement reconciliation for SMS payments.
 *
 * Generates reports of SMS payments for reconciliation with VertexSMS.
 * Tracks settled vs unsettled messages and payment rail breakdown.
 */
class SmsSettlementService
{
    /**
     * Generate a settlement summary for a date range.
     *
     * @return array{
     *   period: array{from: string, to: string},
     *   totals: array{messages: int, parts: int, revenue_usdc: string, settled: int, pending: int},
     *   by_rail: array<string, array{count: int, revenue_usdc: string}>,
     *   by_country: array<string, array{count: int, revenue_usdc: string}>,
     * }
     */
    public function generateReport(Carbon $from, Carbon $to): array
    {
        $messages = SmsMessage::whereBetween('created_at', [$from, $to])
            ->where('test_mode', false)
            ->get();

        $totalRevenue = 0;
        $settled = 0;
        $pending = 0;
        $totalParts = 0;
        /** @var array<string, array{count: int, revenue: int}> $byRail */
        $byRail = [];
        /** @var array<string, array{count: int, revenue: int}> $byCountry */
        $byCountry = [];

        foreach ($messages as $msg) {
            $priceUsdc = (int) $msg->price_usdc;
            $totalRevenue += $priceUsdc;
            $totalParts += (int) $msg->parts;

            if ($msg->payment_receipt !== null) {
                $settled++;
            } else {
                $pending++;
            }

            // By rail
            $rail = (string) ($msg->payment_rail ?? 'unknown');
            if (! isset($byRail[$rail])) {
                $byRail[$rail] = ['count' => 0, 'revenue' => 0];
            }
            $byRail[$rail]['count']++;
            $byRail[$rail]['revenue'] += $priceUsdc;

            // By country
            $country = (string) $msg->country_code;
            if (! isset($byCountry[$country])) {
                $byCountry[$country] = ['count' => 0, 'revenue' => 0];
            }
            $byCountry[$country]['count']++;
            $byCountry[$country]['revenue'] += $priceUsdc;
        }

        // Format revenue values
        /** @var array<string, array{count: int, revenue_usdc: string}> $formattedByRail */
        $formattedByRail = [];
        foreach ($byRail as $rail => $data) {
            $formattedByRail[$rail] = [
                'count'        => $data['count'],
                'revenue_usdc' => (string) $data['revenue'],
            ];
        }

        /** @var array<string, array{count: int, revenue_usdc: string}> $formattedByCountry */
        $formattedByCountry = [];
        foreach ($byCountry as $country => $data) {
            $formattedByCountry[$country] = [
                'count'        => $data['count'],
                'revenue_usdc' => (string) $data['revenue'],
            ];
        }

        Log::info('SMS Settlement: Report generated', [
            'from'     => $from->toIso8601String(),
            'to'       => $to->toIso8601String(),
            'messages' => $messages->count(),
            'revenue'  => $totalRevenue,
        ]);

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ],
            'totals' => [
                'messages'     => $messages->count(),
                'parts'        => $totalParts,
                'revenue_usdc' => (string) $totalRevenue,
                'settled'      => $settled,
                'pending'      => $pending,
            ],
            'by_rail'    => $formattedByRail,
            'by_country' => $formattedByCountry,
        ];
    }

    /**
     * Get unsettled messages that need attention.
     *
     * @return array<int, array{id: string, provider_id: string, to: string, price_usdc: string, created_at: string}>
     */
    public function getUnsettledMessages(int $limit = 50): array
    {
        return SmsMessage::whereNull('payment_receipt')
            ->where('test_mode', false)
            ->where('status', '!=', SmsMessage::STATUS_FAILED)
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (SmsMessage $msg): array => [
                'id'          => (string) $msg->id,
                'provider_id' => (string) $msg->provider_id,
                'to'          => (string) $msg->to,
                'price_usdc'  => (string) $msg->price_usdc,
                'created_at'  => $msg->created_at->toIso8601String(),
            ])
            ->all();
    }
}
