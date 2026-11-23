<?php

declare(strict_types=1);

use App\Domain\Fraud\Models\FraudCase;
use App\Domain\Fraud\Models\FraudScore;
use App\Domain\Regulatory\Services\EnhancedRegulatoryReportingService;
use Illuminate\Support\Facades\Storage;

/*
 * The enhanced SAR read fraud cases by a `priority` column and a `fraudScore`
 * relation that did not exist, and compared risk levels as text, so "low"
 * and "medium" scores counted as high risk.
 */

it('reports high and critical cases and only high-risk scores without a case', function (): void {
    Storage::fake();

    $linked = FraudScore::factory()->create(['risk_level' => FraudScore::RISK_LEVEL_VERY_HIGH, 'total_score' => 95]);
    FraudCase::factory()->create(['severity' => FraudCase::SEVERITY_CRITICAL, 'fraud_score_id' => $linked->id, 'amount' => '250.00']);
    FraudCase::factory()->create(['severity' => FraudCase::SEVERITY_LOW]);

    FraudScore::factory()->create(['risk_level' => FraudScore::RISK_LEVEL_HIGH, 'total_score' => 80]);
    FraudScore::factory()->create(['risk_level' => FraudScore::RISK_LEVEL_LOW, 'total_score' => 20]);
    FraudScore::factory()->create(['risk_level' => FraudScore::RISK_LEVEL_MEDIUM, 'total_score' => 50]);

    $report = app(EnhancedRegulatoryReportingService::class)->generateEnhancedSAR(now()->subDay(), now()->addDay());

    /** @var array<string, mixed> $data */
    $data = json_decode((string) Storage::get((string) $report->file_path), true);
    $caseActivity = collect($data['activities'])->firstWhere('type', 'fraud_case');

    expect($data['fraud_cases'])->toBe(1)
        ->and($data['high_risk_transactions'])->toBe(1)
        ->and($caseActivity['priority'] ?? null)->toBe(5)
        ->and($caseActivity['entity_id'] ?? null)->toBe($linked->entity_id)
        ->and($data['summary']['requires_immediate_filing'] ?? null)->toBeTrue();
});
