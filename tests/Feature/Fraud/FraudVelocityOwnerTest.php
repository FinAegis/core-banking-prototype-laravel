<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Fraud\Services\FraudDetectionService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// Accounts belong to a user through accounts.user_uuid. The velocity checks
// matched accounts.user_id (a nullable treasury link that regular accounts
// leave empty) against users.id, so they always counted zero transactions.

it('counts the transactions of all accounts the user owns today', function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();

    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    Transaction::factory()->forAccount($account)->count(2)->create();

    $other = Account::factory()->create(['user_uuid' => User::factory()->create()->uuid]);
    Transaction::factory()->forAccount($other)->create();

    $service = app(FraudDetectionService::class);
    $count = (new ReflectionMethod($service, 'getDailyTransactionCount'))->invoke($service, $user);

    expect($count)->toBe(2);
});
