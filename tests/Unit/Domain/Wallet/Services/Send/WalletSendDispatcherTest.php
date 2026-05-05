<?php

declare(strict_types=1);

use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\EvmSendDispatcher;
use App\Domain\Wallet\Services\Send\SolanaSendDispatcher;
use App\Domain\Wallet\Services\Send\WalletSendDispatcher;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

it('routes SOLANA (uppercase) to the Solana dispatcher', function (): void {
    /** @var SolanaSendDispatcher&Mockery\MockInterface $solana */
    $solana = Mockery::mock(SolanaSendDispatcher::class);
    /** @var EvmSendDispatcher&Mockery\MockInterface $evm */
    $evm = Mockery::mock(EvmSendDispatcher::class);
    /** @var User&Mockery\MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 7;

    $expected = new WalletSendRecord();
    $solana->shouldReceive('dispatch')
        ->once()
        ->withArgs(function ($u, $recipient, $asset, $amount, $idem, $quote) use ($user) {
            return $u === $user
                && $recipient === 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'
                && $asset === 'USDC'
                && $amount === '1.5'
                && $idem === 'idempotency-key-solana'
                && $quote === 'quote_xyz';
        })
        ->andReturn($expected);

    $evm->shouldNotReceive('dispatch');

    $dispatcher = new WalletSendDispatcher($solana, $evm);
    $result = $dispatcher->dispatch(
        user: $user,
        recipientAddress: 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        assetSymbol: 'USDC',
        networkKey: 'SOLANA',
        amountMajor: '1.5',
        idempotencyKey: 'idempotency-key-solana',
        quoteId: 'quote_xyz',
    );

    expect($result)->toBe($expected);
});

it('routes lowercase solana to the Solana dispatcher (case-insensitive)', function (): void {
    /** @var SolanaSendDispatcher&Mockery\MockInterface $solana */
    $solana = Mockery::mock(SolanaSendDispatcher::class);
    /** @var EvmSendDispatcher&Mockery\MockInterface $evm */
    $evm = Mockery::mock(EvmSendDispatcher::class);
    /** @var User&Mockery\MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    $solana->shouldReceive('dispatch')->once()->andReturn(new WalletSendRecord());
    $evm->shouldNotReceive('dispatch');

    (new WalletSendDispatcher($solana, $evm))->dispatch(
        user: $user,
        recipientAddress: 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        assetSymbol: 'USDC',
        networkKey: 'solana',
        amountMajor: '1',
    );
});

it('routes polygon to the EVM dispatcher with lowercased network key', function (): void {
    /** @var SolanaSendDispatcher&Mockery\MockInterface $solana */
    $solana = Mockery::mock(SolanaSendDispatcher::class);
    /** @var EvmSendDispatcher&Mockery\MockInterface $evm */
    $evm = Mockery::mock(EvmSendDispatcher::class);
    /** @var User&Mockery\MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 9;

    $solana->shouldNotReceive('dispatch');
    $evm->shouldReceive('dispatch')
        ->once()
        ->withArgs(function ($u, $recipient, $asset, $network, $amount, $idem, $quote) {
            return $recipient === '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'
                && $asset === 'USDC'
                && $network === 'polygon'
                && $amount === '25.5';
        })
        ->andReturn(new WalletSendRecord());

    (new WalletSendDispatcher($solana, $evm))->dispatch(
        user: $user,
        recipientAddress: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        assetSymbol: 'USDC',
        networkKey: 'POLYGON',
        amountMajor: '25.5',
    );
});

it('routes each EVM network to the EVM dispatcher', function (): void {
    foreach (['polygon', 'base', 'arbitrum', 'ethereum'] as $network) {
        /** @var SolanaSendDispatcher&Mockery\MockInterface $solana */
        $solana = Mockery::mock(SolanaSendDispatcher::class);
        /** @var EvmSendDispatcher&Mockery\MockInterface $evm */
        $evm = Mockery::mock(EvmSendDispatcher::class);
        /** @var User&Mockery\MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;

        $solana->shouldNotReceive('dispatch');
        $evm->shouldReceive('dispatch')->once()->andReturn(new WalletSendRecord());

        (new WalletSendDispatcher($solana, $evm))->dispatch(
            user: $user,
            recipientAddress: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            assetSymbol: 'USDC',
            networkKey: $network,
            amountMajor: '1',
        );
    }
});
