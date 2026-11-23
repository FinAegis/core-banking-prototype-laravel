<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Fraud\Models\FraudCase;
use App\Domain\Fraud\Models\FraudScore;
use App\Domain\Fraud\Services\FraudCaseService;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/*
 * FraudCaseService was written for columns and constants fraud_cases never
 * had (priority, outcome, entity_*, investigation_notes, …), so every path
 * failed. These tests pin the rewrite onto the real table.
 */

beforeEach(function (): void {
    Event::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function fcsScore(Account $account, array $overrides = []): FraudScore
{
    return FraudScore::factory()->create(array_merge([
        'entity_type'     => App\Domain\Account\Models\Transaction::class,
        'total_score'     => 75,
        'risk_level'      => FraudScore::RISK_LEVEL_HIGH,
        'decision'        => FraudScore::DECISION_REVIEW,
        'triggered_rules' => ['velocity_burst', 'new_device'],
        'entity_snapshot' => [
            'transaction_id' => 4242,
            'amount'         => 125050,
            'currency'       => 'USD',
            'account_id'     => $account->uuid,
            'user_id'        => $account->user_uuid,
        ],
    ], $overrides));
}

it('opens a case from a fraud score with the real columns', function (): void {
    $account = Account::factory()->create();
    $score = fcsScore($account);

    $case = app(FraudCaseService::class)->createFromFraudScore($score);

    expect($case->status)->toBe(FraudCase::STATUS_PENDING)
        ->and($case->severity)->toBe(FraudCase::SEVERITY_CRITICAL)
        ->and($case->type)->toBe(FraudCase::TYPE_TRANSACTION_FRAUD)
        ->and($case->case_number)->toMatch('/^FC-\d{6}-000001$/')
        ->and((string) $case->risk_score)->toBe('75.00')
        ->and($case->detection_rules)->toBe(['velocity_burst', 'new_device'])
        ->and($case->amount)->toBe('1250.50000000')
        ->and($case->currency)->toBe('USD')
        ->and($case->subject_account_uuid)->toBe($account->uuid)
        ->and($case->related_transactions)->toBe([4242])
        ->and($case->detected_at)->not->toBeNull()
        ->and($case->fraud_score_id)->toBe($score->id)
        ->and($case->fraudScore?->is($score))->toBeTrue()
        ->and($score->fresh()?->fraudCase?->is($case))->toBeTrue()
        ->and($case->evidence[0]['type'] ?? null)->toBe('fraud_score')
        ->and($case->actions_taken[0]['action'] ?? null)->toBe('created');
});

it('numbers cases one after another within a month', function (): void {
    $account = Account::factory()->create();
    $service = app(FraudCaseService::class);

    $first = $service->createFromFraudScore(fcsScore($account));
    $second = $service->createFromFraudScore(fcsScore($account));

    expect((int) substr($second->case_number, -6))->toBe((int) substr($first->case_number, -6) + 1);
});

it('raises the severity one level when a case is escalated', function (): void {
    $case = FraudCase::factory()->create(['severity' => FraudCase::SEVERITY_LOW, 'status' => FraudCase::STATUS_PENDING]);
    $service = app(FraudCaseService::class);

    $service->escalateCase($case, 'customer called twice');
    expect($case->fresh()?->severity)->toBe(FraudCase::SEVERITY_MEDIUM);

    $critical = FraudCase::factory()->create(['severity' => FraudCase::SEVERITY_CRITICAL]);
    $service->escalateCase($critical, 'again');
    $critical->refresh();
    expect($critical->severity)->toBe(FraudCase::SEVERITY_CRITICAL)
        ->and(collect($critical->actions_taken)->last()['action'] ?? null)->toBe('escalated')
        ->and(collect($critical->actions_taken)->last()['reason'] ?? null)->toBe('again');
});

it('resolves a confirmed fraud case and records the outcome on the score', function (): void {
    $account = Account::factory()->create();
    $score = fcsScore($account);
    $service = app(FraudCaseService::class);
    $case = $service->createFromFraudScore($score);

    $resolved = $service->resolveCase($case, 'Chargeback confirmed by the bank', 'fraud');

    expect($resolved->status)->toBe(FraudCase::STATUS_CONFIRMED)
        ->and($resolved->resolved_at)->not->toBeNull()
        ->and($resolved->resolution_notes)->toBe('Chargeback confirmed by the bank')
        ->and($score->fresh()?->outcome)->toBe('fraud');
});

it('resolves a legitimate case as a false positive', function (): void {
    $case = FraudCase::factory()->create(['status' => FraudCase::STATUS_INVESTIGATING]);

    $resolved = app(FraudCaseService::class)->resolveCase($case, 'Customer verified the payment', 'legitimate');

    expect($resolved->status)->toBe(FraudCase::STATUS_FALSE_POSITIVE);
});

it('updates an investigation with a note, a status and an assignee', function (): void {
    $case = FraudCase::factory()->create(['status' => FraudCase::STATUS_PENDING, 'assigned_to' => null]);
    $analyst = User::factory()->create();

    $updated = app(FraudCaseService::class)->updateInvestigation($case, [
        'note'        => 'Called the customer',
        'status'      => FraudCase::STATUS_INVESTIGATING,
        'assigned_to' => $analyst->uuid,
        'severity'    => FraudCase::SEVERITY_HIGH,
    ]);

    $actions = collect($updated->actions_taken)->pluck('action')->all();
    expect($updated->status)->toBe(FraudCase::STATUS_INVESTIGATING)
        ->and($updated->assigned_to)->toBe($analyst->uuid)
        ->and($updated->severity)->toBe(FraudCase::SEVERITY_HIGH)
        ->and($actions)->toContain('note')
        ->and($actions)->toContain('status_changed');
});

it('refuses a status the table does not have', function (): void {
    $case = FraudCase::factory()->create(['status' => FraudCase::STATUS_PENDING]);

    app(FraudCaseService::class)->updateInvestigation($case, ['status' => 'open']);
})->throws(InvalidArgumentException::class);

it('searches by status and severity and ignores an unknown sort column', function (): void {
    FraudCase::factory()->create(['status' => FraudCase::STATUS_PENDING, 'severity' => FraudCase::SEVERITY_HIGH]);
    FraudCase::factory()->create(['status' => FraudCase::STATUS_PENDING, 'severity' => FraudCase::SEVERITY_LOW]);
    FraudCase::factory()->create(['status' => FraudCase::STATUS_RESOLVED, 'severity' => FraudCase::SEVERITY_HIGH]);

    $page = app(FraudCaseService::class)->searchCases([
        'status'   => FraudCase::STATUS_PENDING,
        'severity' => FraudCase::SEVERITY_HIGH,
        'sort_by'  => 'id; drop table users',
    ]);

    expect($page->total())->toBe(1);
});

it('counts cases by status and severity', function (): void {
    FraudCase::factory()->create(['status' => FraudCase::STATUS_PENDING, 'severity' => FraudCase::SEVERITY_HIGH]);
    FraudCase::factory()->create(['status' => FraudCase::STATUS_CONFIRMED, 'severity' => FraudCase::SEVERITY_CRITICAL]);

    $stats = app(FraudCaseService::class)->getCaseStatistics();

    expect($stats['total_cases'])->toBe(2)
        ->and($stats['by_status'][FraudCase::STATUS_PENDING])->toBe(1)
        ->and($stats['by_status'][FraudCase::STATUS_CONFIRMED])->toBe(1)
        ->and($stats['by_severity'][FraudCase::SEVERITY_CRITICAL])->toBe(1);
});

it('links cases about the same account', function (): void {
    $account = Account::factory()->create();
    $other = Account::factory()->create();
    $case = FraudCase::factory()->create(['subject_account_uuid' => $account->uuid]);
    $sibling = FraudCase::factory()->create(['subject_account_uuid' => $account->uuid]);
    FraudCase::factory()->create(['subject_account_uuid' => $other->uuid]);

    $similar = app(FraudCaseService::class)->linkSimilarCases($case);

    expect($similar->pluck('id')->all())->toBe([$sibling->id]);
});
