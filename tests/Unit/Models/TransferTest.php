<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Transfer;
use App\Models\Account;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    protected Account $fromAccount;
    protected Account $toAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fromAccount = Account::factory()->create();
        $this->toAccount = Account::factory()->create();
    }

    /** @test */
    public function it_can_create_a_transfer()
    {
        $transfer = Transfer::create([
            'uuid' => 'transfer-uuid',
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'asset_code' => 'USD',
            'amount' => 10000,
            'status' => 'completed',
            'reference' => 'REF123',
            'description' => 'Test transfer',
            'metadata' => ['purpose' => 'testing'],
        ]);

        $this->assertEquals('transfer-uuid', $transfer->uuid);
        $this->assertEquals($this->fromAccount->uuid, $transfer->from_account_uuid);
        $this->assertEquals($this->toAccount->uuid, $transfer->to_account_uuid);
        $this->assertEquals('USD', $transfer->asset_code);
        $this->assertEquals(10000, $transfer->amount);
        $this->assertEquals('completed', $transfer->status);
    }

    /** @test */
    public function it_belongs_to_from_account()
    {
        $transfer = Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
        ]);

        $this->assertInstanceOf(Account::class, $transfer->fromAccount);
        $this->assertEquals($this->fromAccount->uuid, $transfer->fromAccount->uuid);
    }

    /** @test */
    public function it_belongs_to_to_account()
    {
        $transfer = Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
        ]);

        $this->assertInstanceOf(Account::class, $transfer->toAccount);
        $this->assertEquals($this->toAccount->uuid, $transfer->toAccount->uuid);
    }

    /** @test */
    public function it_has_pending_scope()
    {
        Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'status' => 'pending',
        ]);

        Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'status' => 'completed',
        ]);

        $pending = Transfer::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    /** @test */
    public function it_has_completed_scope()
    {
        Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'status' => 'completed',
        ]);

        Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'status' => 'failed',
        ]);

        $completed = Transfer::completed()->get();

        $this->assertCount(1, $completed);
        $this->assertEquals('completed', $completed->first()->status);
    }

    /** @test */
    public function it_has_failed_scope()
    {
        Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'status' => 'failed',
        ]);

        Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'status' => 'completed',
        ]);

        $failed = Transfer::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->status);
    }

    /** @test */
    public function it_has_for_account_scope()
    {
        $otherAccount = Account::factory()->create();

        Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
        ]);

        Transfer::factory()->create([
            'from_account_uuid' => $otherAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
        ]);

        Transfer::factory()->create([
            'from_account_uuid' => $this->toAccount->uuid,
            'to_account_uuid' => $otherAccount->uuid,
        ]);

        $accountTransfers = Transfer::forAccount($this->fromAccount->uuid)->get();

        $this->assertCount(1, $accountTransfers);
        $this->assertEquals($this->fromAccount->uuid, $accountTransfers->first()->from_account_uuid);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $transfer = Transfer::create([
            'uuid' => 'test-uuid',
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
            'asset_code' => 'USD',
            'amount' => 10000,
            'status' => 'completed',
            'metadata' => ['key' => 'value'],
            'completed_at' => '2025-06-18 12:00:00',
        ]);

        $fresh = Transfer::find($transfer->uuid);

        $this->assertIsArray($fresh->metadata);
        $this->assertEquals('value', $fresh->metadata['key']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $fresh->completed_at);
    }

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $transfer = Transfer::factory()->create([
            'from_account_uuid' => $this->fromAccount->uuid,
            'to_account_uuid' => $this->toAccount->uuid,
        ]);

        $this->assertEquals('uuid', $transfer->getKeyName());
        $this->assertFalse($transfer->getIncrementing());
        $this->assertEquals('string', $transfer->getKeyType());
    }
}