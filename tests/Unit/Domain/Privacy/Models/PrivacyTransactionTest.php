<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Models;

use App\Domain\Privacy\Models\PrivacyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PrivacyTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_fillable_fields(): void
    {
        $tx = new PrivacyTransaction();
        $fillable = $tx->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('tx_hash', $fillable);
        $this->assertContains('operation', $fillable);
        $this->assertContains('calldata', $fillable);
        $this->assertContains('to_address', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('recipient', $fillable);
        $this->assertContains('metadata', $fillable);
    }

    public function test_calldata_is_encrypted_cast(): void
    {
        $tx = new PrivacyTransaction();
        $casts = $tx->getCasts();

        $this->assertSame('encrypted', $casts['calldata']);
    }

    public function test_metadata_is_json_cast(): void
    {
        $tx = new PrivacyTransaction();
        $casts = $tx->getCasts();

        $this->assertSame('json', $casts['metadata']);
    }

    public function test_status_constants(): void
    {
        $this->assertSame('pending', PrivacyTransaction::STATUS_PENDING);
        $this->assertSame('submitted', PrivacyTransaction::STATUS_SUBMITTED);
        $this->assertSame('confirmed', PrivacyTransaction::STATUS_CONFIRMED);
        $this->assertSame('failed', PrivacyTransaction::STATUS_FAILED);
    }

    public function test_scope_for_user(): void
    {
        $user = User::factory()->create();

        PrivacyTransaction::create([
            'user_id'    => $user->id,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '100.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('ab', 20),
            'calldata'   => '0xdeadbeef',
            'status'     => PrivacyTransaction::STATUS_PENDING,
        ]);

        $otherUser = User::factory()->create();
        PrivacyTransaction::create([
            'user_id'    => $otherUser->id,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '50.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('cd', 20),
            'calldata'   => '0xcafebabe',
            'status'     => PrivacyTransaction::STATUS_PENDING,
        ]);

        $results = PrivacyTransaction::query()->forUser($user->id)->get();
        $this->assertCount(1, $results);
        $this->assertEquals($user->id, $results->first()->user_id);
    }

    public function test_scope_for_network(): void
    {
        $user = User::factory()->create();

        PrivacyTransaction::create([
            'user_id'    => $user->id,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '100.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('ab', 20),
            'calldata'   => '0xdeadbeef',
            'status'     => PrivacyTransaction::STATUS_PENDING,
        ]);

        PrivacyTransaction::create([
            'user_id'    => $user->id,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '50.00',
            'network'    => 'ethereum',
            'to_address' => '0x' . str_repeat('cd', 20),
            'calldata'   => '0xcafebabe',
            'status'     => PrivacyTransaction::STATUS_PENDING,
        ]);

        $results = PrivacyTransaction::query()->forNetwork('polygon')->get();
        $this->assertCount(1, $results);
        $this->assertSame('polygon', $results->first()->network);
    }

    public function test_scope_for_tx_hash(): void
    {
        $user = User::factory()->create();
        $txHash = '0x' . str_repeat('11', 32);

        PrivacyTransaction::create([
            'user_id'    => $user->id,
            'tx_hash'    => $txHash,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '100.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('ab', 20),
            'calldata'   => '0xdeadbeef',
            'status'     => PrivacyTransaction::STATUS_SUBMITTED,
        ]);

        $results = PrivacyTransaction::query()->forTxHash($txHash)->get();
        $this->assertCount(1, $results);
        $this->assertSame($txHash, $results->first()->tx_hash);
    }

    public function test_to_api_response(): void
    {
        $user = User::factory()->create();

        $tx = PrivacyTransaction::create([
            'user_id'      => $user->id,
            'tx_hash'      => '0x' . str_repeat('11', 32),
            'operation'    => 'shield',
            'token'        => 'USDC',
            'amount'       => '100.00',
            'network'      => 'polygon',
            'to_address'   => '0x' . str_repeat('ab', 20),
            'calldata'     => '0xdeadbeef',
            'value'        => '0',
            'gas_estimate' => '150000',
            'status'       => PrivacyTransaction::STATUS_PENDING,
            'recipient'    => null,
        ]);

        $response = $tx->toApiResponse();

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('tx_hash', $response);
        $this->assertArrayHasKey('operation', $response);
        $this->assertArrayHasKey('calldata', $response);
        $this->assertArrayHasKey('to_address', $response);
        $this->assertArrayHasKey('created_at', $response);
        $this->assertSame('shield', $response['operation']);
        $this->assertSame('USDC', $response['token']);
        $this->assertSame('100.00', $response['amount']);
        $this->assertSame('polygon', $response['network']);
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();

        $tx = PrivacyTransaction::create([
            'user_id'    => $user->id,
            'operation'  => 'shield',
            'token'      => 'USDC',
            'amount'     => '100.00',
            'network'    => 'polygon',
            'to_address' => '0x' . str_repeat('ab', 20),
            'calldata'   => '0xdeadbeef',
            'status'     => PrivacyTransaction::STATUS_PENDING,
        ]);

        $this->assertInstanceOf(User::class, $tx->user);
        $this->assertEquals($user->id, $tx->user->id);
    }
}
