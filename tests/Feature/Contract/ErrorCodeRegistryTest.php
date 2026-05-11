<?php

/**
 * Contract test for the Plan B error code registry. Ships with v1.3.0
 * foundations and is a hard merge gate going forward — every new code
 * added in later PRs must satisfy these assertions.
 *
 * @see config/error_codes.php
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md Q15.3
 */

declare(strict_types=1);

use App\Support\ErrorResponse;

const PLAN_B_ERROR_CODE_REGEX = '/^ERR_[A-Z]+_\d{3}$/';

it('every registered error code matches /^ERR_[A-Z]+_\d{3}$/', function (): void {
    /** @var array<string, array{http: int, description: string}> $registry */
    $registry = config('error_codes', []);

    expect($registry)->not->toBeEmpty('error_codes registry is empty — config/error_codes.php must be loaded.');

    foreach (array_keys($registry) as $code) {
        expect($code)->toMatch(PLAN_B_ERROR_CODE_REGEX);
    }
});

it('every registered error code has http + description fields', function (): void {
    /** @var array<string, array{http: int, description: string}> $registry */
    $registry = config('error_codes', []);

    foreach ($registry as $code => $entry) {
        expect($entry)->toHaveKey('http');
        expect($entry)->toHaveKey('description');
        expect($entry['http'])->toBeInt();
        expect($entry['description'])->toBeString();
        expect($entry['description'])->not()->toBeEmpty("Description for {$code} is empty");
    }
});

it('every registered http status is in the 4xx-5xx range', function (): void {
    /** @var array<string, array{http: int, description: string}> $registry */
    $registry = config('error_codes', []);

    foreach ($registry as $code => $entry) {
        expect($entry['http'])->toBeGreaterThanOrEqual(400)
            ->toBeLessThan(600, "HTTP status for {$code} must be 4xx or 5xx");
    }
});

it('ErrorResponse::make returns the registered http status and shape', function (): void {
    $response = ErrorResponse::make('ERR_VALIDATION_001');

    /** @var array{success: bool, error: array{code: string, message: string}} $data */
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(422);
    expect($data['success'])->toBeFalse();
    expect($data['error']['code'])->toBe('ERR_VALIDATION_001');
    expect($data['error']['message'])->toBeString();
    expect($data['error']['message'])->not()->toBeEmpty();
});

it('ErrorResponse::make merges context into the error object', function (): void {
    $response = ErrorResponse::make(
        'ERR_SUB_002',
        null,
        ['existingSource' => 'apple_iap'],
    );

    expect($response->getStatusCode())->toBe(409);

    /** @var array{success: bool, error: array<string, mixed>} $data */
    $data = $response->getData(true);

    expect($data['error']['code'])->toBe('ERR_SUB_002')
        ->and($data['error']['existingSource'])->toBe('apple_iap');
});

it('ErrorResponse::make honours messageOverride', function (): void {
    $response = ErrorResponse::make('ERR_QUOTE_001', 'Your quote expired 5 seconds ago.');

    /** @var array{error: array{message: string}} $data */
    $data = $response->getData(true);

    expect($data['error']['message'])->toBe('Your quote expired 5 seconds ago.');
});

it('ErrorResponse::make throws on unknown code', function (): void {
    ErrorResponse::make('ERR_NOT_REGISTERED_999');
})->throws(LogicException::class, 'Unknown error code');

it('the registry contains the deltas Q15.3 mandatory codes', function (): void {
    /** @var array<string, mixed> $registry */
    $registry = config('error_codes', []);

    // Spot-check that the codes the spec calls out are present.
    $mandatoryCodes = [
        'ERR_VALIDATION_001',
        'ERR_VALIDATION_002',
        'ERR_VALIDATION_003',
        'ERR_IDEMPOTENCY_409',
        'ERR_CUR_001',
        'ERR_SUB_001',
        'ERR_SUB_002',
        'ERR_QUOTE_001',
        'ERR_QUOTE_002',
        'ERR_QUO_002',
        'ERR_FEE_001',
        'ERR_EXP_001',
    ];

    foreach ($mandatoryCodes as $code) {
        expect($registry)->toHaveKey($code);
    }
});
