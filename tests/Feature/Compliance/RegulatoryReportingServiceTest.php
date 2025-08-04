<?php

declare(strict_types=1);

use App\Domain\Account\Events\MoneyAdded;
use App\Domain\Account\Models\Account;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Services\RegulatoryReportingService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent as StoredEvent;

beforeEach(function () {
    Storage::fake();
    $this->reportingService = app(RegulatoryReportingService::class);

    // Create test users with different KYC statuses
    $this->users = [
        'approved'  => User::factory()->create(['kyc_status' => 'approved', 'risk_rating' => 'low']),
        'pending'   => User::factory()->create(['kyc_status' => 'pending', 'kyc_submitted_at' => now()]),
        'high_risk' => User::factory()->create(['kyc_status' => 'approved', 'risk_rating' => 'high', 'pep_status' => true]),
    ];

    // Create accounts
    $this->accounts = [];
    foreach ($this->users as $key => $user) {
        $this->accounts[$key] = Account::factory()->forUser($user)->create();
    }
});

test('generates currency transaction report for large transactions', function () {
    $date = now();

    // Create a large transaction (over $10,000)
    $largeEvent = new StoredEvent();
    $largeEvent->aggregate_uuid = $this->accounts['approved']->uuid;
    $largeEvent->aggregate_version = 1;
    $largeEvent->event_version = 1;
    $largeEvent->event_class = MoneyAdded::class;
    $largeEvent->event_properties = [
        'money' => ['amount' => 1500000, 'currency' => 'USD'], // $15,000
        'hash'  => ['value' => hash('sha3-512', 'test')],
    ];
    /** @phpstan-ignore-next-line */
    $largeEvent->meta_data = [];
    $largeEvent->created_at = $date;
    $largeEvent->save();

    // Create a small transaction (under threshold)
    $smallEvent = new StoredEvent();
    $smallEvent->aggregate_uuid = $this->accounts['approved']->uuid;
    $smallEvent->aggregate_version = 2;
    $smallEvent->event_version = 1;
    $smallEvent->event_class = MoneyAdded::class;
    $smallEvent->event_properties = [
        'money' => ['amount' => 50000, 'currency' => 'USD'], // $500
        'hash'  => ['value' => hash('sha3-512', 'test2')],
    ];
    /** @phpstan-ignore-next-line */
    $smallEvent->meta_data = [];
    $smallEvent->created_at = $date;
    $smallEvent->save();

    $filename = $this->reportingService->generateCTR($date);

    expect(Storage::exists($filename))->toBeTrue();

    $report = json_decode(Storage::get($filename), true);
    expect($report['report_type'])->toBe('Currency Transaction Report (CTR)');
    expect($report['total_transactions'])->toBe(1); // Only large transaction
    expect($report['transactions'])->toHaveCount(1);
    expect($report['transactions'][0]['amount'])->toBe(1500000);

    // Check audit log
    $log = AuditLog::where('action', 'regulatory.ctr_generated')->first();
    expect($log)->not->toBeNull();
});

test('detects suspicious patterns for SAR candidates', function () {
    $startDate = now()->subDays(7);
    $endDate = now();

    // Create rapid succession transactions (potential structuring)
    $account = $this->accounts['high_risk'];
    for ($i = 0; $i < 15; $i++) {
        $event = new StoredEvent();
        $event->aggregate_uuid = $account->uuid;
        $event->aggregate_version = $i + 1;
        $event->event_version = 1;
        $event->event_class = MoneyAdded::class;
        $event->event_properties = [
            'money' => ['amount' => 950000, 'currency' => 'USD'], // Just under $10k
            'hash'  => ['value' => hash('sha3-512', "test{$i}")],
        ];
        /** @phpstan-ignore-next-line */
        $event->meta_data = [];
        $event->created_at = $startDate->copy()->addHours($i);
        $event->save();
    }

    $filename = $this->reportingService->generateSARCandidates($startDate, $endDate);

    expect(Storage::exists($filename))->toBeTrue();

    $report = json_decode(Storage::get($filename), true);
    expect($report['report_type'])->toBe('Suspicious Activity Report (SAR) Candidates');
    expect($report['total_candidates'])->toBeGreaterThan(0);

    // Should detect both rapid transactions and threshold avoidance patterns
    $patterns = collect($report['patterns']);
    expect($patterns->pluck('pattern_type')->unique()->values())->toContain('rapid_transactions', 'threshold_avoidance');
});

test('generates comprehensive compliance summary', function () {
    $month = now()->startOfMonth();

    // Create some test data
    User::factory()->count(3)->create([
        'created_at'       => $month->copy()->addDays(5),
        'kyc_status'       => 'approved',
        'kyc_submitted_at' => $month->copy()->addDays(3),
        'kyc_approved_at'  => $month->copy()->addDays(5),
    ]);

    $filename = $this->reportingService->generateComplianceSummary($month);

    expect(Storage::exists($filename))->toBeTrue();

    $report = json_decode(Storage::get($filename), true);
    expect($report['report_type'])->toBe('Monthly Compliance Summary');
    expect($report['month'])->toBe($month->format('F Y'));
    expect($report['metrics'])->toHaveKeys(['kyc', 'transactions', 'users', 'risk', 'gdpr']);

    // Check user metrics - we created 3 new users plus the 3 from beforeEach
    expect($report['metrics']['users']['new_users'])->toBeGreaterThanOrEqual(3);
    expect($report['metrics']['kyc']['approved'])->toBeGreaterThanOrEqual(3);
});

test('generates KYC compliance report', function () {
    // Create users with different KYC statuses
    User::factory()->count(5)->create(['kyc_status' => 'approved']);
    User::factory()->count(3)->create(['kyc_status' => 'pending', 'kyc_submitted_at' => now()->subDays(2)]);
    User::factory()->count(2)->create(['kyc_status' => 'rejected']);
    User::factory()->count(1)->create(['kyc_status' => 'expired']);
    User::factory()->count(2)->create(['pep_status' => true, 'kyc_status' => 'approved']);

    $filename = $this->reportingService->generateKycReport();

    expect(Storage::exists($filename))->toBeTrue();

    $report = json_decode(Storage::get($filename), true);
    expect($report['report_type'])->toBe('KYC Compliance Report');
    expect($report['statistics']['kyc_status_breakdown']['approved'])->toBeGreaterThanOrEqual(5);
    expect($report['statistics']['kyc_status_breakdown']['pending'])->toBeGreaterThanOrEqual(3);
    expect($report['statistics']['pep_users'])->toBeGreaterThanOrEqual(2);
    expect($report['pending_verifications'])->toBeArray();
});

test('regulatory reporting command works correctly', function () {
    $this->artisan('compliance:generate-reports --type=kyc')
        ->expectsOutput('🏛️ Generating Regulatory Reports')
        ->expectsOutput('🆔 Generating KYC Compliance Report...')
        ->expectsOutputToContain('✅ KYC report generated:')
        ->expectsOutput('✅ All reports generated successfully!')
        ->assertSuccessful();
});

test('detects round number transaction patterns', function () {
    $startDate = now()->subDays(7);
    $endDate = now();
    $account = $this->accounts['approved'];

    // Create multiple round number transactions
    $amounts = [1000000, 2000000, 5000000, 10000000]; // $10k, $20k, $50k, $100k
    foreach ($amounts as $i => $amount) {
        for ($j = 0; $j < 2; $j++) { // Create 2 of each to meet threshold
            $event = new StoredEvent();
            $event->aggregate_uuid = $account->uuid;
            $event->aggregate_version = ($i * 2) + $j + 1;
            $event->event_version = 1;
            $event->event_class = MoneyAdded::class;
            $event->event_properties = [
                'money' => ['amount' => $amount, 'currency' => 'USD'],
                'hash'  => ['value' => hash('sha3-512', "test{$i}{$j}")],
            ];
            /** @phpstan-ignore-next-line */
            $event->meta_data = [];
            $event->created_at = $startDate->copy()->addDays($i);
            $event->save();
        }
    }

    $filename = $this->reportingService->generateSARCandidates($startDate, $endDate);
    $report = json_decode(Storage::get($filename), true);

    $patterns = collect($report['patterns']);
    $roundNumberPattern = $patterns->firstWhere('pattern_type', 'round_numbers');

    expect($roundNumberPattern)->not->toBeNull();
    expect($roundNumberPattern['transaction_count'])->toBeGreaterThanOrEqual(5);
});
