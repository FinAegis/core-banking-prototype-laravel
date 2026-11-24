<?php

namespace Tests\Domain\Account\Projectors;

use App\Domain\Account\Aggregates\AssetTransactionAggregate;
use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Utils\ValidatesHash;
use DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @property Account $account
 * @property \App\Models\User $business_user
 */
class AccountProjectorTest extends TestCase
{
    use ValidatesHash;

    #[Test]
    public function test_create(): void
    {
        $this->assertDatabaseHas((new Account())->getTable(), [
            'user_uuid' => $this->business_user->uuid,
            'uuid'      => $this->account->uuid,
        ]);

        $this->assertTrue($this->account->user->is($this->business_user));
    }

    #[Test]
    public function test_add_money(): void
    {
        // Ensure account starts with 0 balance by clearing any existing balance
        AccountBalance::where('account_uuid', $this->account->uuid)->delete();
        $this->account->refresh();
        $this->assertEquals(0, $this->account->balance);
        $this->resetHash();

        // Use a completely fresh aggregate UUID to avoid any existing state
        $freshUuid = Str::uuid()->toString();
        DB::table('accounts')->where('uuid', $this->account->uuid)->update(['uuid' => $freshUuid]);
        $this->account->uuid = $freshUuid;
        $this->account->save();
        $this->account->refresh();

        AssetTransactionAggregate::retrieve($freshUuid)
            ->credit('USD', 10)
            ->persist();

        $this->account->refresh();

        $this->assertSame(10, $this->account->balance);
    }

    #[Test]
    public function test_subtract_money(): void
    {
        // Ensure account starts with 0 balance by clearing any existing balance
        AccountBalance::where('account_uuid', $this->account->uuid)->delete();
        $this->account->refresh();
        $this->assertEquals(0, $this->account->balance);
        $this->resetHash();

        // Use aggregate to create initial balance and then subtract
        AssetTransactionAggregate::retrieve($this->account->uuid)
            ->credit('USD', 20)
            ->debit('USD', 10)
            ->persist();

        $this->account->refresh();

        $this->assertSame(10, $this->account->balance);
    }

    #[Test]
    public function test_delete_account(): void
    {
        LedgerAggregate::retrieve($this->account->uuid)
            ->deleteAccount()
            ->persist();

        $this->assertDatabaseMissing((new Account())->getTable(), [
            'user_uuid' => $this->business_user->uuid,
            'uuid'      => $this->account->uuid,
        ]);
    }
}
