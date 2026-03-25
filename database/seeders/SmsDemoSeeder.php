<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\SMS\Models\SmsMessage;
use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Domain\X402\Models\X402SpendingLimit;
use Illuminate\Database\Seeder;

/**
 * Seeds demo data for the SMS domain:
 * - X402 monetized endpoint for POST /api/v1/sms/send
 * - Agent spending limits for demo SMS usage
 * - Sample SMS messages in various delivery statuses
 *
 * @see app/Domain/SMS/ — VertexSMS integration
 * @see docs/BACKEND_PRODUCTION_HANDOVER.md (vertexsms-partnership)
 */
class SmsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMonetizedEndpoint();
        $this->seedSpendingLimits();
        $this->seedSampleMessages();
    }

    /**
     * Register the SMS send endpoint as an x402-monetized resource.
     *
     * Price: $0.05 USDC per request (50 000 atomic units, 6 decimals).
     */
    private function seedMonetizedEndpoint(): void
    {
        X402MonetizedEndpoint::firstOrCreate(
            [
                'method' => 'POST',
                'path'   => '/api/v1/sms/send',
            ],
            [
                'price'       => '50000',
                'network'     => 'eip155:8453',
                'asset'       => 'USDC',
                'scheme'      => 'exact',
                'description' => 'Send an SMS via VertexSMS (per-message pricing)',
                'mime_type'   => 'application/json',
                'is_active'   => true,
                'extra'       => [
                    'provider'         => 'vertexsms',
                    'supports_testnet' => true,
                    'rate_card_url'    => '/api/v1/sms/rates',
                ],
            ],
        );
    }

    /**
     * Create default spending limits for the demo SMS agent.
     *
     * - Daily budget: $10 USDC (10 000 000 atomic)
     * - Per-tx limit: $0.50 USDC (500 000 atomic) — covers multi-part
     * - Auto-pay enabled so the demo flow is frictionless
     */
    private function seedSpendingLimits(): void
    {
        X402SpendingLimit::firstOrCreate(
            [
                'agent_id'   => 'demo-sms-agent',
                'agent_type' => 'sms',
            ],
            [
                'daily_limit'           => '10000000',
                'spent_today'           => '0',
                'per_transaction_limit' => '500000',
                'auto_pay_enabled'      => true,
                'limit_resets_at'       => now()->addDay(),
            ],
        );
    }

    /**
     * Insert sample SMS messages across all four statuses so
     * dashboards and API consumers see realistic data immediately.
     */
    private function seedSampleMessages(): void
    {
        $messages = [
            // --- delivered ---
            [
                'provider'     => 'vertexsms',
                'provider_id'  => 'demo-vtx-001',
                'to'           => '+37069912345',
                'from'         => 'Zelta',
                'message'      => 'Your OTP code is 483920. Valid for 5 minutes.',
                'parts'        => 1,
                'status'       => SmsMessage::STATUS_DELIVERED,
                'price_usdc'   => '48000',
                'country_code' => 'LT',
                'payment_rail' => 'x402',
                'payment_id'   => 'demo-pay-001',
                'test_mode'    => true,
                'delivered_at' => now()->subHours(2),
            ],
            [
                'provider'     => 'vertexsms',
                'provider_id'  => 'demo-vtx-002',
                'to'           => '+49170123456',
                'from'         => 'Zelta',
                'message'      => 'Payment of 250.00 GCU received. Ref: TXN-8829.',
                'parts'        => 1,
                'status'       => SmsMessage::STATUS_DELIVERED,
                'price_usdc'   => '72000',
                'country_code' => 'DE',
                'payment_rail' => 'x402',
                'payment_id'   => 'demo-pay-002',
                'test_mode'    => true,
                'delivered_at' => now()->subHours(5),
            ],
            // --- sent (awaiting DLR) ---
            [
                'provider'     => 'vertexsms',
                'provider_id'  => 'demo-vtx-003',
                'to'           => '+34612345678',
                'from'         => 'Zelta',
                'message'      => 'Your weekly account summary is ready. Log in to view.',
                'parts'        => 1,
                'status'       => SmsMessage::STATUS_SENT,
                'price_usdc'   => '55000',
                'country_code' => 'ES',
                'payment_rail' => 'mpp',
                'payment_id'   => 'demo-pay-003',
                'test_mode'    => true,
            ],
            [
                'provider'     => 'vertexsms',
                'provider_id'  => 'demo-vtx-004',
                'to'           => '+33612345678',
                'from'         => 'Zelta',
                'message'      => 'Suspicious login attempt detected. Reply BLOCK to freeze account.',
                'parts'        => 1,
                'status'       => SmsMessage::STATUS_SENT,
                'price_usdc'   => '58000',
                'country_code' => 'FR',
                'payment_rail' => 'x402',
                'payment_id'   => 'demo-pay-004',
                'test_mode'    => true,
            ],
            // --- failed ---
            [
                'provider'     => 'vertexsms',
                'provider_id'  => 'demo-vtx-005',
                'to'           => '+447911123456',
                'from'         => 'Zelta',
                'message'      => 'Your card ending in 4829 has been activated.',
                'parts'        => 1,
                'status'       => SmsMessage::STATUS_FAILED,
                'price_usdc'   => '62000',
                'country_code' => 'GB',
                'payment_rail' => 'x402',
                'payment_id'   => 'demo-pay-005',
                'test_mode'    => true,
            ],
            // --- pending ---
            [
                'provider'     => 'vertexsms',
                'provider_id'  => 'demo-vtx-006',
                'to'           => '+37069998877',
                'from'         => 'Zelta',
                'message'      => 'Reminder: Your GCU staking rewards have been distributed.',
                'parts'        => 1,
                'status'       => SmsMessage::STATUS_PENDING,
                'price_usdc'   => '48000',
                'country_code' => 'LT',
                'payment_rail' => 'mpp',
                'payment_id'   => 'demo-pay-006',
                'test_mode'    => true,
            ],
            // --- multi-part message ---
            [
                'provider'     => 'vertexsms',
                'provider_id'  => 'demo-vtx-007',
                'to'           => '+37069912345',
                'from'         => 'Zelta',
                'message'      => 'Dear valued customer, your cross-border transfer of 1,500.00 EUR to DE89370400440532013000 has been processed. The exchange rate used was 1 EUR = 1.0842 GCU. Settlement will complete within 2 business days. Reference: XFR-20260324-7891. Contact support@zelta.app for questions.',
                'parts'        => 2,
                'status'       => SmsMessage::STATUS_DELIVERED,
                'price_usdc'   => '96000',
                'country_code' => 'LT',
                'payment_rail' => 'x402',
                'payment_id'   => 'demo-pay-007',
                'test_mode'    => true,
                'delivered_at' => now()->subDay(),
            ],
        ];

        foreach ($messages as $msg) {
            SmsMessage::firstOrCreate(
                ['provider_id' => $msg['provider_id']],
                $msg,
            );
        }
    }
}
