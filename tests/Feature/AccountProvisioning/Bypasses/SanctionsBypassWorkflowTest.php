<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AgentProtocol\DataObjects\KycVerificationRequest;
use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use App\Domain\AgentProtocol\Workflows\Activities\PerformAmlScreeningActivity;
use App\Models\User;
use ReflectionClass;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * End-to-end wiring test: a {@see KycVerificationRequest} carrying a
 * reviewer's user_id must reach the AML activity's bypass branch.
 *
 * Shape B (DTO + activity) was chosen over Shape A (full Workflow engine
 * run) because there are no existing AgentKycWorkflow Pest tests in this
 * repo to mirror, and spinning up the workflow runtime per assertion is
 * heavy compared to verifying the DTO carries `userId` through to the
 * activity that consumes it.
 */
function instantiateAmlActivityForWorkflowTest(): PerformAmlScreeningActivity
{
    /** @var PerformAmlScreeningActivity $activity */
    $activity = (new ReflectionClass(PerformAmlScreeningActivity::class))
        ->newInstanceWithoutConstructor();

    return $activity;
}

it('passes review_bypass through KycVerificationRequest into the AML screening activity', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                    => $user->id,
        'is_review_account'          => true,
        'bypass_sanctions_screening' => true,
    ]);

    $request = new KycVerificationRequest(
        agentId: 'agent-wf-1',
        agentDid: 'did:agent:wf-1',
        agentName: 'App Reviewer',
        verificationLevel: KycVerificationLevel::BASIC,
        documents: [],
        countryCode: 'US',
        userId: (int) $user->id,
    );

    // The DTO must round-trip user_id so the workflow engine can serialize it.
    expect($request->userId)->toBe((int) $user->id);
    expect($request->toArray())->toHaveKey('user_id', (int) $user->id);
    expect(KycVerificationRequest::fromArray($request->toArray())->userId)->toBe((int) $user->id);

    $activity = instantiateAmlActivityForWorkflowTest();

    // Mirror the named-arg dispatch that AgentKycWorkflow performs:
    //     Activity::make(PerformAmlScreeningActivity::class, [
    //         'agentId' => ..., 'agentName' => ..., 'countryCode' => ...,
    //         'userId' => $request->userId,
    //     ]);
    $result = $activity->execute(
        agentId: $request->agentId,
        agentName: $request->agentName,
        countryCode: $request->countryCode,
        userId: $request->userId,
    );

    expect($result['source'])->toBe('review_bypass');
    expect($result['status'])->toBe('passed');
    expect($result['hasAlerts'])->toBeFalse();
});

it('runs full sanctions screening when KycVerificationRequest carries no userId', function () {
    $request = new KycVerificationRequest(
        agentId: 'agent-wf-2',
        agentDid: 'did:agent:wf-2',
        agentName: 'Regular Agent',
        verificationLevel: KycVerificationLevel::BASIC,
        documents: [],
        countryCode: 'US',
        // userId omitted — null by default
    );

    expect($request->userId)->toBeNull();
    expect($request->toArray())->toHaveKey('user_id', null);

    $activity = instantiateAmlActivityForWorkflowTest();

    $result = $activity->execute(
        agentId: $request->agentId,
        agentName: $request->agentName,
        countryCode: $request->countryCode,
        userId: $request->userId,
    );

    // Bypass path is unreachable without userId; a clean name + safe country
    // produces a normal "passed" screening with no source sentinel.
    expect($result)->not->toHaveKey('source');
    expect($result['status'])->toBe('passed');
    expect($result['hasAlerts'])->toBeFalse();
});

it('runs full sanctions screening when reviewer user has no AccountFlag', function () {
    // User exists but has no account_flags row at all → bypass path is skipped.
    $user = User::factory()->create();

    $request = new KycVerificationRequest(
        agentId: 'agent-wf-3',
        agentDid: 'did:agent:wf-3',
        agentName: 'App Reviewer',
        verificationLevel: KycVerificationLevel::BASIC,
        documents: [],
        countryCode: 'US',
        userId: (int) $user->id,
    );

    $activity = instantiateAmlActivityForWorkflowTest();

    $result = $activity->execute(
        agentId: $request->agentId,
        agentName: $request->agentName,
        countryCode: $request->countryCode,
        userId: $request->userId,
    );

    expect($result)->not->toHaveKey('source');
    expect($result['status'])->toBe('passed');
});
