<?php

/**
 * GraphQL initiateCustodianTransfer must hand the custodian service the exact
 * minor units of the transfer asset. The resolver used (int) ($amount * 100),
 * which turned 0.29 into 28 cents and assumed two decimals for every asset.
 */

declare(strict_types=1);

use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianTransfer;
use App\Domain\Custodian\Services\CustodianAccountService;
use App\Models\User;
use Mockery\MockInterface;

beforeEach(function (): void {
    config(['ledger.direct_funding_enabled' => true]);

    Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);

    $this->owner = User::factory()->create();
    $this->from = Account::factory()->create(['user_uuid' => $this->owner->uuid]);
    $this->to = Account::factory()->create(['user_uuid' => $this->owner->uuid]);
    CustodianAccount::query()->create(['account_uuid' => $this->from->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-1', 'status' => 'active', 'is_primary' => true]);
    CustodianAccount::query()->create(['account_uuid' => $this->to->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-2', 'status' => 'active', 'is_primary' => true]);

    /** @var ArrayObject<int, int> $amounts */
    $amounts = new ArrayObject();
    $this->amounts = $amounts;

    /** @var CustodianAccountService&MockInterface $service */
    $service = Mockery::mock(CustodianAccountService::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $service->shouldReceive('initiateTransfer');
    $expectation->andReturnUsing(function (CustodianAccount $fromLink, CustodianAccount $toLink, Money $amount, string $assetCode) use ($amounts): string {
        $amounts->append($amount->getAmount());
        $id = 'custodian-transfer-' . count($amounts);
        CustodianTransfer::query()->create([
            'id'                        => $id,
            'from_account_uuid'         => $fromLink->account_uuid,
            'to_account_uuid'           => $toLink->account_uuid,
            'from_custodian_account_id' => $fromLink->id,
            'to_custodian_account_id'   => $toLink->id,
            'amount'                    => $amount->getAmount(),
            'asset_code'                => $assetCode,
            'status'                    => 'pending',
            'transfer_type'             => 'internal',
        ]);

        return $id;
    });
    app()->instance(CustodianAccountService::class, $service);
});

/**
 * @return array<string, mixed>
 */
function custodianTransferAmountRequest(PHPUnit\Framework\TestCase $test, User $user, Account $from, Account $to, float $amount, string $assetCode): array
{
    $response = $test->actingAs($user, 'sanctum')->postJson('/graphql', [
        'query'     => 'mutation ($i: InitiateCustodianTransferInput!) { initiateCustodianTransfer(input: $i) { id } }',
        'variables' => ['i' => [
            'from_account_uuid' => $from->uuid,
            'to_account_uuid'   => $to->uuid,
            'amount'            => $amount,
            'asset_code'        => $assetCode,
        ]],
    ]);
    $response->assertOk();

    /** @var array<string, mixed> $json */
    $json = $response->json();

    return $json;
}

it('passes the exact minor units of the asset to the custodian service', function (float $amount, string $assetCode, int $minor): void {
    $json = custodianTransferAmountRequest($this, $this->owner, $this->from, $this->to, $amount, $assetCode);

    expect($json)->not->toHaveKey('errors')
        ->and($this->amounts->getArrayCopy())->toBe([$minor]);
})->with([
    '0.29 EUR is 29 cents'      => [0.29, 'EUR', 29],
    '128.14 EUR is 12814 cents' => [128.14, 'EUR', 12814],
    '1.1 BTC is 110000000 sat'  => [1.1, 'BTC', 110000000],
]);

it('refuses an amount with more decimals than the asset has', function (): void {
    $json = custodianTransferAmountRequest($this, $this->owner, $this->from, $this->to, 0.295, 'EUR');

    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    expect($errors[0]['message'] ?? null)->toBe('The amount can have at most 2 decimal places for EUR.')
        ->and($this->amounts->getArrayCopy())->toBe([]);
});

it('refuses a zero or negative amount', function (float $amount): void {
    $json = custodianTransferAmountRequest($this, $this->owner, $this->from, $this->to, $amount, 'EUR');

    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    expect($errors[0]['message'] ?? null)->toBe('The amount must be greater than zero.')
        ->and($this->amounts->getArrayCopy())->toBe([]);
})->with(['zero' => [0.0], 'negative' => [-5.0]]);
