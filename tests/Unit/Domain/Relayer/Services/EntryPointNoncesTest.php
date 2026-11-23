<?php

declare(strict_types=1);

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\EntryPointNonces;
use App\Domain\Relayer\Services\EthRpcClient;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

it('reads the next nonce of a smart account from the EntryPoint', function (): void {
    /** @var EthRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(EthRpcClient::class);
    $rpc->shouldReceive('ethCall')
        ->once()
        ->with(SupportedNetwork::BASE, [
            'to'   => SupportedNetwork::BASE->getEntryPointAddress(),
            'data' => '0x35567e1a'
                . str_pad('1111111111111111111111111111111111111111', 64, '0', STR_PAD_LEFT)
                . str_repeat('0', 64),
        ])
        ->andReturn('0x' . str_pad('2a', 64, '0', STR_PAD_LEFT));

    expect((new EntryPointNonces($rpc))->sequence('0x1111111111111111111111111111111111111111', SupportedNetwork::BASE))->toBe(42);
});

it('refuses an answer that is not a small number', function (): void {
    /** @var EthRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(EthRpcClient::class);
    $rpc->shouldReceive('ethCall')->once()->andReturn('0x');

    (new EntryPointNonces($rpc))->sequence('0x1111111111111111111111111111111111111111', SupportedNetwork::BASE);
})->throws(RuntimeException::class);
