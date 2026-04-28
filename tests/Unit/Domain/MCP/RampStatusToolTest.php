<?php

declare(strict_types=1);

use App\Domain\MCP\Tools\Ramp\RampStatusTool;
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
function rampStatusServiceMock(): RampService
{
    /** @var RampService&MockInterface $m */
    $m = Mockery::mock(RampService::class);

    return $m;
}

it('returns the refreshed session status when session belongs to the caller', function () {
    $user = User::factory()->create();
    Auth::shouldReceive('guard->user')->andReturn($user);
    Auth::shouldReceive('user')->andReturn($user);

    $session = RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => '100',
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabc',
        'status'              => RampSession::STATUS_PENDING,
        'provider_session_id' => 'sess_remote_1',
    ]);

    $service = rampStatusServiceMock();
    $service->shouldReceive('getSessionStatus')
        ->once()
        ->with(Mockery::on(fn ($s) => $s instanceof RampSession && $s->id === $session->id))
        ->andReturnUsing(function (RampSession $s) {
            $s->status = RampSession::STATUS_COMPLETED;
            $s->crypto_amount = 99.5;

            return $s;
        });

    $tool = new RampStatusTool($service);
    $result = $tool->execute(['session_id' => (string) $session->id]);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getData()['session_id'])->toBe((string) $session->id);
    expect($result->getData()['status'])->toBe('completed');
    expect($result->getData()['provider'])->toBe('stripe_bridge');
    expect((float) $result->getData()['crypto_amount'])->toBe(99.5);
});

it('refuses to leak existence of another user\'s session', function () {
    $owner = User::factory()->create();
    $caller = User::factory()->create();
    Auth::shouldReceive('guard->user')->andReturn($caller);
    Auth::shouldReceive('user')->andReturn($caller);

    $session = RampSession::create([
        'user_id'             => $owner->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => '100',
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabc',
        'status'              => RampSession::STATUS_PENDING,
        'provider_session_id' => 'sess_remote_2',
    ]);

    $service = rampStatusServiceMock();
    $service->shouldNotReceive('getSessionStatus');

    $tool = new RampStatusTool($service);
    $result = $tool->execute(['session_id' => (string) $session->id]);

    expect($result->isSuccess())->toBeFalse();
    expect($result->getError())->toContain('not found');
});

it('returns NOT_FOUND error when no session matches the id', function () {
    $user = User::factory()->create();
    Auth::shouldReceive('guard->user')->andReturn($user);
    Auth::shouldReceive('user')->andReturn($user);

    $service = rampStatusServiceMock();
    $service->shouldNotReceive('getSessionStatus');

    $tool = new RampStatusTool($service);
    $result = $tool->execute(['session_id' => '99999999']);

    expect($result->isSuccess())->toBeFalse();
    expect($result->getError())->toContain('not found');
});

it('exposes static metadata that matches catalog config', function () {
    $service = rampStatusServiceMock();
    $tool = new RampStatusTool($service);

    expect($tool->getName())->toBe('ramp.status');
    expect($tool->getCategory())->toBe('ramp');
    expect($tool->getInputSchema()['required'])->toBe(['session_id']);
    expect($tool->isCacheable())->toBeFalse();
});
