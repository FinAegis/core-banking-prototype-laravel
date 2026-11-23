<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\JournalLine;
use App\Models\User;
use App\Support\DecimalString;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Cache::forget('domain_manager_disabled');
    config(['modules.disabled' => []]);

    Role::firstOrCreate(['name' => 'bank_admin', 'guard_name' => 'web']);
    $operator = User::factory()->create();
    $operator->assignRole('bank_admin');
    Sanctum::actingAs($operator, ['read', 'write', 'delete']);

    $this->postJson('/api/v1/ledger/accounts', ['code' => '9300', 'name' => 'Amount asset', 'type' => 'asset'])->assertCreated();
    $this->postJson('/api/v1/ledger/accounts', ['code' => '9301', 'name' => 'Amount liability', 'type' => 'liability'])->assertCreated();
});

it('posts an entry with a JSON float that PHP prints in exponent form', function (): void {
    // The JSON number 0.00005 decodes to a float that PHP prints as "5.0E-5",
    // which bcadd() rejected with a ValueError (HTTP 500). At four decimals
    // it truncates to zero, as bcadd('0.00005', '0', 4) does.
    $this->postJson('/api/v1/ledger/entries', [
        'description' => 'Exponent float',
        'lines'       => [
            ['account_code' => '9300', 'debit' => 10, 'credit' => 0],
            ['account_code' => '9301', 'debit' => 0.00005, 'credit' => 10],
        ],
    ])->assertCreated();

    $line = JournalLine::query()->where('credit_amount', '>', 0)->sole();
    // SQLite hands the decimal column back as a number, MySQL as a string.
    expect(DecimalString::fromNumeric($line->debit_amount, 4))->toBe('0.0000')
        ->and(DecimalString::fromNumeric($line->credit_amount, 4))->toBe('10.0000');
});

it('posts an entry whose amounts are numeric strings in exponent form', function (): void {
    // "1e3" passes the numeric rule, but bcadd() rejected it with a ValueError.
    $this->postJson('/api/v1/ledger/entries', [
        'description' => 'Exponent strings',
        'lines'       => [
            ['account_code' => '9300', 'debit' => '1e3', 'credit' => '0'],
            ['account_code' => '9301', 'debit' => '0', 'credit' => '1e3'],
        ],
    ])->assertCreated();

    $line = JournalLine::query()->where('credit_amount', '>', 0)->sole();
    expect(DecimalString::fromNumeric($line->credit_amount, 4))->toBe('1000.0000');
});
