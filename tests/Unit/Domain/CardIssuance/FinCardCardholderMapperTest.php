<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Support\FinCardCardholderMapper;

it('maps snake_case onboarding fields to FinCard camelCase payload', function () {
    $payload = FinCardCardholderMapper::toPayload([
        'first_name'              => 'Jane',
        'last_name'               => 'Smith',
        'birthday'                => '1992-03-20',
        'nationality'             => 'US',
        'annual_salary'           => '50k-100k',
        'expected_monthly_volume' => '1k-5k',
        'zip_code'                => '90001',
        'id_type'                 => 'PASSPORT',
        'id_front_file_id'        => 'file-1',
        'id_selfie_file_id'       => 'file-3',
    ], '203.0.113.7');

    expect($payload['firstName'])->toBe('Jane')
        ->and($payload['lastName'])->toBe('Smith')
        ->and($payload['annualSalary'])->toBe('50k-100k')
        ->and($payload['expectedMonthlyVolume'])->toBe('1k-5k')
        ->and($payload['zipCode'])->toBe('90001')
        ->and($payload['idFrontId'])->toBe('file-1')
        ->and($payload['idHoldId'])->toBe('file-3')
        ->and($payload['ipAddress'])->toBe('203.0.113.7');
});

it('drops empty/null fields from the payload', function () {
    $payload = FinCardCardholderMapper::toPayload([
        'first_name' => 'Jane',
        'last_name'  => '',
        'state'      => null,
    ], '127.0.0.1');

    expect($payload)->toHaveKey('firstName')
        ->and($payload)->not->toHaveKey('lastName')
        ->and($payload)->not->toHaveKey('state');
});

it('persists only the non-sensitive attribute subset', function () {
    $persisted = FinCardCardholderMapper::persistedAttributes([
        'gender'           => 'female',
        'birthday'         => '1992-03-20',
        'id_number'        => 'SECRET123',
        'id_front_file_id' => 'file-1',
    ]);

    expect($persisted)->toHaveKeys(['gender', 'birthday'])
        ->and($persisted)->not->toHaveKey('id_number')
        ->and($persisted)->not->toHaveKey('id_front_file_id');
});

it('flags restricted countries case-insensitively', function () {
    expect(FinCardCardholderMapper::isRestrictedCountry('RU'))->toBeTrue()
        ->and(FinCardCardholderMapper::isRestrictedCountry('ir'))->toBeTrue()
        ->and(FinCardCardholderMapper::isRestrictedCountry('US'))->toBeFalse();
});
