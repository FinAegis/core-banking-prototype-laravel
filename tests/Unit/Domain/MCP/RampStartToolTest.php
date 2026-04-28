<?php

declare(strict_types=1);

use App\Domain\MCP\Tools\Ramp\RampStartTool;
use App\Domain\Ramp\Services\RampService;
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return RampService&MockInterface
 */
function rampServiceMock(): RampService
{
    /** @var RampService&MockInterface $m */
    $m = Mockery::mock(RampService::class);

    return $m;
}

it('returns a checkout URL when RampService creates the session', function () {
    $user = User::factory()->create();
    Auth::shouldReceive('guard->user')->andReturn($user);
    Auth::shouldReceive('user')->andReturn($user);

    $session = new RampSession();
    $session->id = 'sess-uuid-1';
    $session->provider = 'stripe_bridge';
    $session->status = RampSession::STATUS_PENDING;
    $session->metadata = ['checkout_url' => 'https://checkout.stripe.com/abc'];

    $service = rampServiceMock();
    $service->shouldReceive('createSession')->once()->andReturn($session);

    $tool = new RampStartTool($service);
    $result = $tool->execute([
        'type'            => 'on',
        'fiat_currency'   => 'USD',
        'fiat_amount'     => '100',
        'crypto_currency' => 'USDC',
        'wallet_address'  => '0xabc',
    ]);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getData())->toMatchArray([
        'session_id'   => 'sess-uuid-1',
        'checkout_url' => 'https://checkout.stripe.com/abc',
        'provider'     => 'stripe_bridge',
        'status'       => 'pending',
    ]);
});

it('fails gracefully when no user is bound to the bearer token', function () {
    Auth::shouldReceive('guard->user')->andReturn(null);
    Auth::shouldReceive('user')->andReturn(null);

    $service = rampServiceMock();
    $service->shouldNotReceive('createSession');

    $tool = new RampStartTool($service);
    $result = $tool->execute([
        'type'            => 'on',
        'fiat_currency'   => 'USD',
        'fiat_amount'     => '100',
        'crypto_currency' => 'USDC',
        'wallet_address'  => '0xabc',
    ]);

    expect($result->isSuccess())->toBeFalse();
    expect($result->getError())->toContain('Unauthenticated');
});

it('returns failure when RampService throws', function () {
    $user = User::factory()->create();
    Auth::shouldReceive('guard->user')->andReturn($user);

    $service = rampServiceMock();
    $service->shouldReceive('createSession')->once()->andThrow(new RuntimeException('provider unavailable'));

    $tool = new RampStartTool($service);
    $result = $tool->execute([
        'type'            => 'off',
        'fiat_currency'   => 'EUR',
        'fiat_amount'     => '50',
        'crypto_currency' => 'EURC',
        'wallet_address'  => '0xdef',
    ]);

    expect($result->isSuccess())->toBeFalse();
    expect($result->getError())->toContain('provider unavailable');
});

it('exposes static metadata that matches catalog config', function () {
    $service = rampServiceMock();
    $tool = new RampStartTool($service);

    expect($tool->getName())->toBe('ramp.start');
    expect($tool->getCategory())->toBe('ramp');
    expect($tool->getInputSchema()['required'])->toContain('type', 'fiat_currency', 'fiat_amount', 'crypto_currency', 'wallet_address');
    expect($tool->isCacheable())->toBeFalse();
});
