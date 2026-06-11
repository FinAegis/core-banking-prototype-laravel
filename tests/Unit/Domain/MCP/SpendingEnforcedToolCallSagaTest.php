<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\MCP;

use App\Domain\MCP\Exceptions\SpendingLimitExceededException;
use App\Domain\MCP\Policy\SpendingLimitService;
use App\Domain\MCP\Sagas\SpendingEnforcedToolCallSaga;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class);

// SpendingLimitService is final (not mockable under type hints), so — like
// SpendingLimitPolicyTest and ToolsCallTest — the saga is exercised against
// the service's real mcp_token_policies backing.

function sagaSpendMinor(): int
{
    return (int) DB::table('mcp_token_policies')->where('token_id', 'tok_saga')->value('daily_spend_minor');
}

beforeEach(function () {
    DB::table('mcp_token_policies')->insert([
        'token_id'              => 'tok_saga',
        'daily_limit_minor'     => 50000, // $500.00
        'daily_limit_currency'  => 'USD',
        'daily_spend_minor'     => 0,
        'daily_window_start_at' => now(),
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    $this->saga = new SpendingEnforcedToolCallSaga(new SpendingLimitService());
});

// ---------------------------------------------------------------------------
// ramp.start — variable amount from fiat_amount/fiat_currency arguments
// ---------------------------------------------------------------------------

it('reserves the fiat amount in minor units for the real ramp.start catalog entry', function () {
    // Use the REAL catalog entry so this test breaks if config/mcp.php and the
    // saga's reader (amount_arg / currency_arg / amount_decimals) drift apart.
    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['ramp.start'];
    expect($entry['is_payment'] ?? false)->toBeTrue();

    $result = $this->saga->run('tok_saga', [
        'type'            => 'on',
        'fiat_amount'     => '100.00',
        'fiat_currency'   => 'USD',
        'crypto_currency' => 'USDC',
        'wallet_address'  => '0xabc',
    ], $entry, fn (): array => ['isError' => false, 'content' => []]);

    expect($result['isError'])->toBeFalse();
    // '100.00' major × amount_decimals=2 → 10000 minor reserved and kept.
    expect(sagaSpendMinor())->toBe(10000);
});

it('releases the ramp.start reservation when the tool reports an error', function () {
    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['ramp.start'];

    $result = $this->saga->run('tok_saga', [
        'fiat_amount'   => '100.00',
        'fiat_currency' => 'USD',
    ], $entry, fn (): array => ['isError' => true, 'content' => []]);

    expect($result['isError'])->toBeTrue();
    expect(sagaSpendMinor())->toBe(0);
});

it('releases the ramp.start reservation and rethrows when the tool throws', function () {
    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['ramp.start'];

    try {
        $this->saga->run('tok_saga', [
            'fiat_amount'   => '100.00',
            'fiat_currency' => 'USD',
        ], $entry, function (): array {
            throw new RuntimeException('rail down');
        });
        $this->fail('Expected RuntimeException');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('rail down');
    }

    expect(sagaSpendMinor())->toBe(0);
});

it('throws SpendingLimitExceededException without executing when ramp.start exceeds the cap', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_saga')->update(['daily_spend_minor' => 45000]);

    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['ramp.start'];

    $executed = false;

    try {
        // 45000 spent + 10000 requested > 50000 limit → reject.
        $this->saga->run('tok_saga', [
            'fiat_amount'   => '100.00',
            'fiat_currency' => 'USD',
        ], $entry, function () use (&$executed): array {
            $executed = true;

            return ['isError' => false];
        });
        $this->fail('Expected SpendingLimitExceededException');
    } catch (SpendingLimitExceededException $e) {
        expect($e->data['amount_requested_minor'])->toBe(10000);
        expect($e->data['currency'])->toBe('USD');
        expect($e->data['limit_remaining_minor'])->toBe(5000);
    }

    expect($executed)->toBeFalse();
    expect(sagaSpendMinor())->toBe(45000);
});

it('rejects with CURRENCY_MISMATCH when ramp.start fiat_currency differs from the policy currency', function () {
    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['ramp.start'];

    $executed = false;

    try {
        $this->saga->run('tok_saga', [
            'fiat_amount'   => '100.00',
            'fiat_currency' => 'EUR', // policy is USD — proves currency_arg is wired through
        ], $entry, function () use (&$executed): array {
            $executed = true;

            return ['isError' => false];
        });
        $this->fail('Expected SpendingLimitExceededException');
    } catch (SpendingLimitExceededException $e) {
        expect($e->data['error_code'])->toBe('CURRENCY_MISMATCH');
    }

    expect($executed)->toBeFalse();
    expect(sagaSpendMinor())->toBe(0);
});

// ---------------------------------------------------------------------------
// sms.send — flat fixed_cost_minor per call, no amount in the arguments
// ---------------------------------------------------------------------------

it('reserves the flat fixed cost for the real sms.send catalog entry without any amount arguments', function () {
    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['sms.send'];
    expect($entry['is_payment'] ?? false)->toBeTrue();
    expect($entry['fixed_cost_minor'] ?? null)->toBe(10);

    // No amount/currency anywhere in the arguments — the charge comes from the catalog.
    $result = $this->saga->run('tok_saga', [
        'to'      => '+37069912345',
        'message' => 'hello',
    ], $entry, fn (): array => ['isError' => false, 'content' => []]);

    expect($result['isError'])->toBeFalse();
    expect(sagaSpendMinor())->toBe(10);
});

it('releases the fixed sms.send cost when the tool reports an error', function () {
    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['sms.send'];

    $result = $this->saga->run('tok_saga', [
        'to'      => '+37069912345',
        'message' => 'hello',
    ], $entry, fn (): array => ['isError' => true, 'content' => []]);

    expect($result['isError'])->toBeTrue();
    expect(sagaSpendMinor())->toBe(0);
});

it('defaults the fixed-cost currency to USD when fixed_cost_currency is omitted', function () {
    // Flip the policy to EUR: if the saga defaults the omitted currency to
    // USD (as documented), the reserve must fail with CURRENCY_MISMATCH.
    DB::table('mcp_token_policies')->where('token_id', 'tok_saga')->update(['daily_limit_currency' => 'EUR']);

    $entry = [
        'is_payment'       => true,
        'fixed_cost_minor' => 25,
        // no fixed_cost_currency
    ];

    try {
        $this->saga->run('tok_saga', [], $entry, fn (): array => ['isError' => false]);
        $this->fail('Expected SpendingLimitExceededException');
    } catch (SpendingLimitExceededException $e) {
        expect($e->data['error_code'])->toBe('CURRENCY_MISMATCH');
        expect($e->data['currency'])->toBe('USD');
        expect($e->data['amount_requested_minor'])->toBe(25);
    }
});

it('throws SpendingLimitExceededException without executing when the fixed-cost reservation is rejected', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_saga')->delete();

    /** @var array<string, mixed> $entry */
    $entry = config('mcp.tools')['sms.send'];

    $executed = false;

    try {
        $this->saga->run('tok_saga', ['to' => '+1', 'message' => 'x'], $entry, function () use (&$executed): array {
            $executed = true;

            return ['isError' => false];
        });
        $this->fail('Expected SpendingLimitExceededException');
    } catch (SpendingLimitExceededException $e) {
        expect($e->data['error_code'])->toBe('NO_POLICY');
        expect($e->data['amount_requested_minor'])->toBe(10);
    }

    expect($executed)->toBeFalse();
});
