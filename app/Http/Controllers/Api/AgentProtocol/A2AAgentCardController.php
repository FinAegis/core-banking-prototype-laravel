<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\DataObjects\A2AAgentCard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class A2AAgentCardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $card = new A2AAgentCard(
            name: (string) config('brand.name', 'Zelta'),
            description: 'AI agent interface for FinAegis — payments, escrow, compliance, and trading',
            url: (string) config('app.url'),
            version: '1.0.0',
            skills: [
                [
                    'id'          => 'payment',
                    'name'        => 'Payment Processing',
                    'description' => 'Initiate and manage payments across multiple rails including x402, MPP, and Stripe',
                ],
                [
                    'id'          => 'sms',
                    'name'        => 'SMS Payments',
                    'description' => 'Send and receive payments via SMS using multi-rail routing',
                ],
                [
                    'id'          => 'escrow',
                    'name'        => 'Escrow Management',
                    'description' => 'Create, fund, release, and dispute escrow agreements between agents',
                ],
                [
                    'id'          => 'trading',
                    'name'        => 'Asset Trading',
                    'description' => 'Execute asset trades, manage baskets, and access exchange rates',
                ],
                [
                    'id'          => 'compliance',
                    'name'        => 'Compliance & KYC',
                    'description' => 'KYC verification, AML screening, and regulatory compliance checks',
                ],
            ],
            defaultInputModes: ['text', 'application/json'],
            defaultOutputModes: ['application/json'],
            authentication: [
                'schemes' => ['bearer', 'apiKey'],
            ],
            supportsStreaming: true,
            supportsPushNotifications: false,
        );

        return response()->json($card->toArray());
    }
}
