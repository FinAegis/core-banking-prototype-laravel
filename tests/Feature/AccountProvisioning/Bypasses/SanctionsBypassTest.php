<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AgentProtocol\Workflows\Activities\PerformAmlScreeningActivity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Instantiate the Activity bypassing its parent Workflow\Activity constructor
 * so we can exercise the execute() method directly under Pest.
 */
function instantiateAmlActivity(): PerformAmlScreeningActivity
{
    /** @var PerformAmlScreeningActivity $activity */
    $activity = (new ReflectionClass(PerformAmlScreeningActivity::class))
        ->newInstanceWithoutConstructor();

    return $activity;
}

it('short-circuits sanctions screening for review accounts with bypass flag', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                    => $user->id,
        'is_review_account'          => true,
        'bypass_sanctions_screening' => true,
    ]);

    $logSpy = Log::spy();

    $activity = instantiateAmlActivity();

    // Use a name + country that would normally trigger sanctions + high-risk
    // jurisdiction. The bypass short-circuits BEFORE any screening runs.
    $result = $activity->execute(
        agentId: 'agent-123',
        agentName: 'John Doe Sanctioned',
        countryCode: 'KP',
        userId: (int) $user->id,
    );

    expect($result['status'])->toBe('passed');
    expect($result['hasAlerts'])->toBeFalse();
    expect($result['alerts'])->toBe([]);
    expect($result['riskFactors'])->toBe([]);
    expect($result['source'])->toBe('review_bypass');

    $logSpy->shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($user) {
        return $message === 'bypass.fired'
            && ($context['user_id'] ?? null) === $user->id
            && ($context['bypass'] ?? null) === 'sanctions_screening';
    })->atLeast()->once();
});

it('runs full sanctions screening when no user is provided', function () {
    $activity = instantiateAmlActivity();

    // No userId — bypass path is skipped and real screening runs.
    $result = $activity->execute(
        agentId: 'agent-123',
        agentName: 'Evil Corp',
        countryCode: 'KP',
    );

    expect($result['hasAlerts'])->toBeTrue();
    expect($result['status'])->toBe('alerts_found');
    expect($result)->not->toHaveKey('source');
});
