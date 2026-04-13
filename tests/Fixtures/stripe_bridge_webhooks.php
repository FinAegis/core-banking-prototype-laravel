<?php

/**
 * Realistic Stripe Crypto Onramp webhook event fixtures.
 *
 * These mirror the shape of events Stripe actually sends, used across
 * StripeBridgeRampTest and RampProviderContractTest.
 *
 * @see https://docs.stripe.com/crypto/onramp
 */

declare(strict_types=1);

return [
    'session_updated' => [
        'id'      => 'evt_test_updated_123',
        'type'    => 'crypto_onramp_session.updated',
        'object'  => 'event',
        'created' => 1743500000,
        'data'    => [
            'object' => [
                'id'                   => 'cos_test_abc123',
                'object'               => 'crypto.onramp_session',
                'status'               => 'payment_pending',
                'source_currency'      => 'usd',
                'source_amount'        => '100.00',
                'destination_currency' => 'usdc',
                'destination_network'  => 'ethereum',
                'destination_amount'   => null,
                'wallet_addresses'     => [
                    'ethereum' => '0x1234567890abcdef1234567890abcdef12345678',
                ],
            ],
        ],
    ],

    'session_completed' => [
        'id'      => 'evt_test_completed_456',
        'type'    => 'crypto_onramp_session.completed',
        'object'  => 'event',
        'created' => 1743500100,
        'data'    => [
            'object' => [
                'id'                   => 'cos_test_abc123',
                'object'               => 'crypto.onramp_session',
                'status'               => 'fulfilled',
                'source_currency'      => 'usd',
                'source_amount'        => '100.00',
                'destination_currency' => 'usdc',
                'destination_network'  => 'ethereum',
                'destination_amount'   => '98.50000000',
                'wallet_addresses'     => [
                    'ethereum' => '0x1234567890abcdef1234567890abcdef12345678',
                ],
            ],
        ],
    ],

    'unrelated_event' => [
        'id'      => 'evt_test_unrelated_789',
        'type'    => 'payment_intent.succeeded',
        'object'  => 'event',
        'created' => 1743500200,
        'data'    => [
            'object' => [
                'id'     => 'pi_test_xyz',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 10000,
            ],
        ],
    ],

    'session_without_id' => [
        'id'      => 'evt_test_noid_000',
        'type'    => 'crypto_onramp_session.updated',
        'object'  => 'event',
        'created' => 1743500300,
        'data'    => [
            'object' => [
                'object' => 'crypto.onramp_session',
                'status' => 'initialized',
            ],
        ],
    ],
];
