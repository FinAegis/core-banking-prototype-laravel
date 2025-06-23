<?php

namespace Tests\Domain\Account\Projectors;

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\Aggregates\TransactionAggregate;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Events\MoneyAdded;
use App\Domain\Account\Repositories\TransactionRepository;
use App\Domain\Account\Utils\ValidatesHash;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AccountProjectorTest extends TestCase
{
    use ValidatesHash;

    #[Test]
    public function test_create(): void
    {
        $this->assertDatabaseHas((new Account())->getTable(), [
            'user_uuid' => $this->business_user->uuid,
            'uuid' => $this->account->uuid,
        ]);

        $this->assertTrue($this->account->user->is($this->business_user));
    }

    #[Test]
    public function test_add_money(): void
    {
        $this->assertEquals(0, $this->account->balance);
        $this->resetHash();

        TransactionAggregate::retrieve($this->account->uuid)
            ->credit(
                hydrate( Money::class, ['amount' => 10] )
            )
            ->persist();

        $this->account->refresh();

        $this->assertEquals(10, $this->account->balance);
    }

    #[Test]
    public function test_subtract_money(): void
    {
        $this->assertEquals(0, $this->account->balance);
        $this->resetHash();

        $this->account->addMoney(new Money(20));

        TransactionAggregate::retrieve($this->account->uuid)
            ->applyMoneyAdded( new MoneyAdded(
                money: $money = hydrate( Money::class, ['amount' => 20] ),
                hash: $this->generateHash($money)
            ) )
            ->debit(
                hydrate( Money::class, ['amount' => 10] )
            )->persist();

        $this->account->refresh();

        $this->assertEquals(10, $this->account->balance);
    }

    #[Test]
    public function test_delete_account(): void
    {
        LedgerAggregate::retrieve($this->account->uuid)
            ->deleteAccount()
            ->persist();

        $this->assertDatabaseMissing((new Account())->getTable(), [
            'user_uuid' => $this->business_user->uuid,
            'uuid' => $this->account->uuid,
        ]);
    }
}
