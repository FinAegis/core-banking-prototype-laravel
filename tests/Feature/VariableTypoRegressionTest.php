<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Newsletter\Services\SubscriberEmailService;
use App\Domain\Wallet\Workflows\Activities\BlockchainWithdrawalActivities;
use Illuminate\Support\Str;

/*
 * A July 2025 static-analysis clean-up turned about sixty assignments such as
 * `$account = Account::where(...)->first();` into variable-variables
 * (`$$account = ...`). Where the variable was initialised to null first, the
 * code silently found nothing; elsewhere it threw "Undefined variable". These
 * tests pin a few of the restored paths and keep the typo from coming back.
 */

it('updates the asset balance when an asset transaction is recorded', function (): void {
    // AssetTransactionProjector used to log "Account not found" and skip the
    // balance update for every asset transaction.
    $account = Account::factory()->create(['frozen' => false]);

    AssetTransactionAggregate::retrieve((string) Str::uuid())
        ->credit(
            accountUuid: new AccountUuid($account->uuid),
            assetCode: 'EUR',
            money: new Money(700),
            description: 'Regression test credit',
        )
        ->persist();

    expect($account->fresh()?->getBalance('EUR'))->toBe(700);
});

it('marks a subscriber as bounced', function (): void {
    // SubscriberEmailService::handleBounce never found the subscriber, so a
    // bounced address kept receiving newsletters.
    $subscriber = Subscriber::create([
        'email'  => 'bounce@example.com',
        'source' => Subscriber::SOURCE_BLOG,
        'status' => Subscriber::STATUS_ACTIVE,
        'tags'   => [],
    ]);

    (new SubscriberEmailService())->handleBounce('bounce@example.com');

    expect($subscriber->fresh()?->status)->toBe(Subscriber::STATUS_BOUNCED);
});

it('refuses blockchain withdrawals that require two-factor verification', function (): void {
    // The check was a placeholder that the typo made throw on every call;
    // with the typo fixed it must still refuse instead of accepting any code.
    $activities = (new ReflectionClass(BlockchainWithdrawalActivities::class))->newInstanceWithoutConstructor();

    expect(fn () => $activities->verifyTwoFactorCode('1', '123456'))
        ->toThrow(Exception::class, 'Two-factor verification is not available');
});

it('has no variable-variables left in the application code', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            if (preg_match('/\$\$[A-Za-z_]/', $line) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
