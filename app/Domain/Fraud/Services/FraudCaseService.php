<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Asset\Models\Asset;
use App\Domain\Fraud\Events\FraudCaseCreated;
use App\Domain\Fraud\Events\FraudCaseResolved;
use App\Domain\Fraud\Events\FraudCaseUpdated;
use App\Domain\Fraud\Models\FraudCase;
use App\Domain\Fraud\Models\FraudScore;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Opens and works fraud cases on the fraud_cases table.
 *
 * What the table has no column for (the fraud score's details, investigator
 * notes, escalations, links to similar cases) goes into the two JSON logs the
 * table keeps: `evidence` and `actions_taken`.
 */
class FraudCaseService
{
    private const array STATUSES = [
        FraudCase::STATUS_PENDING,
        FraudCase::STATUS_INVESTIGATING,
        FraudCase::STATUS_CONFIRMED,
        FraudCase::STATUS_FALSE_POSITIVE,
        FraudCase::STATUS_RESOLVED,
    ];

    private const array SEVERITIES = [
        FraudCase::SEVERITY_LOW,
        FraudCase::SEVERITY_MEDIUM,
        FraudCase::SEVERITY_HIGH,
        FraudCase::SEVERITY_CRITICAL,
    ];

    private const array SORTABLE = ['created_at', 'detected_at', 'resolved_at', 'risk_score', 'amount', 'severity', 'status', 'case_number'];

    public function __construct(
        private readonly MachineLearningService $machineLearning,
    ) {
    }

    /**
     * Open a case for a high-risk fraud score.
     */
    public function createFromFraudScore(FraudScore $fraudScore): FraudCase
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = is_array($fraudScore->entity_snapshot) ? $fraudScore->entity_snapshot : [];
        $isTransaction = $fraudScore->entity_type === Transaction::class;
        $currency = is_string($snapshot['currency'] ?? null) ? $snapshot['currency'] : null;
        $accountUuid = is_string($snapshot['account_id'] ?? null) ? $snapshot['account_id'] : null;
        /** @var list<string> $rules */
        $rules = array_values(array_filter((array) ($fraudScore->triggered_rules ?? []), 'is_string'));

        $case = FraudCase::create([
            'case_number'          => $this->generateCaseNumber(),
            'type'                 => $isTransaction ? FraudCase::TYPE_TRANSACTION_FRAUD : FraudCase::TYPE_OTHER,
            'status'               => FraudCase::STATUS_PENDING,
            'severity'             => $this->severityFor($fraudScore),
            'risk_score'           => $fraudScore->total_score,
            'detection_rules'      => $rules,
            'amount'               => $isTransaction ? $this->majorUnits($snapshot['amount'] ?? null, $currency) : null,
            'currency'             => $isTransaction ? $currency : null,
            'subject_account_uuid' => $accountUuid,
            'related_transactions' => isset($snapshot['transaction_id']) ? [$snapshot['transaction_id']] : [],
            'related_accounts'     => $accountUuid !== null ? [$accountUuid] : [],
            'description'          => sprintf(
                'Opened by fraud detection: %s risk, score %s, decision %s.',
                (string) $fraudScore->risk_level,
                (string) $fraudScore->total_score,
                (string) $fraudScore->decision,
            ),
            'evidence' => [[
                'type'             => 'fraud_score',
                'fraud_score_id'   => $fraudScore->id,
                'entity_type'      => $fraudScore->entity_type,
                'entity_id'        => $fraudScore->entity_id,
                'risk_level'       => $fraudScore->risk_level,
                'risk_factors'     => $this->riskFactors($fraudScore),
                'entity_snapshot'  => $snapshot,
                'initial_decision' => $fraudScore->decision,
                'added_at'         => now()->toIso8601String(),
            ]],
            'detected_at'    => now(),
            'actions_taken'  => [['action' => 'created', 'by' => 'system', 'timestamp' => now()->toIso8601String()]],
            'fraud_score_id' => $fraudScore->id,
        ]);

        event(new FraudCaseCreated($case));

        return $case;
    }

    /**
     * Record investigation work: a note, evidence, a status, an assignee
     * (user uuid) or a severity. `priority` is accepted as the old name for
     * severity.
     *
     * @param array<string, mixed> $data
     */
    public function updateInvestigation(FraudCase $case, array $data): FraudCase
    {
        $by = $this->actorName();

        if (is_string($data['note'] ?? null) && $data['note'] !== '') {
            $case->recordAction('note', ['note' => $data['note'], 'type' => $data['note_type'] ?? 'investigation', 'by' => $by]);
        }

        if (is_array($data['evidence'] ?? null)) {
            $case->addEvidence(array_merge($data['evidence'], ['added_by' => $by]));
        }

        if (is_string($data['status'] ?? null) && $data['status'] !== $case->status) {
            $status = $this->assertStatus($data['status']);
            $from = $case->status;
            $case->update(['status' => $status]);
            $case->recordAction('status_changed', ['from' => $from, 'to' => $status, 'by' => $by]);
        }

        $severity = $data['severity'] ?? $data['priority'] ?? null;
        if (is_string($severity) && $severity !== $case->severity) {
            $case->update(['severity' => $this->assertSeverity($severity)]);
        }

        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== $case->assigned_to) {
            $case->update(['assigned_to' => is_string($data['assigned_to']) ? $data['assigned_to'] : null]);
        }

        event(new FraudCaseUpdated($case));

        return $case->refresh();
    }

    /**
     * Close a case. `fraud` confirms it, `legitimate` marks a false positive,
     * anything else resolves it without a finding. The fraud score that
     * opened the case gets the same outcome.
     */
    public function resolveCase(FraudCase $case, string $resolution, string $outcome): FraudCase
    {
        $status = match ($outcome) {
            FraudScore::OUTCOME_FRAUD      => FraudCase::STATUS_CONFIRMED,
            FraudScore::OUTCOME_LEGITIMATE => FraudCase::STATUS_FALSE_POSITIVE,
            default                        => FraudCase::STATUS_RESOLVED,
        };

        $case->update([
            'status'           => $status,
            'resolution_notes' => $resolution,
            'resolved_at'      => now(),
        ]);
        $case->recordAction('resolved', ['outcome' => $outcome, 'by' => $this->actorName()]);

        $score = $case->fraudScore;
        if ($score instanceof FraudScore) {
            $score->update(['outcome' => $outcome]);
            $this->machineLearning->trainWithFeedback($score, $outcome);
        }

        if ($outcome === FraudScore::OUTCOME_FRAUD) {
            $this->flagAccountOwner($case);
        }

        event(new FraudCaseResolved($case));

        return $case->refresh();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, FraudCase>
     */
    public function searchCases(array $filters): LengthAwarePaginator
    {
        $query = FraudCase::query();

        foreach (['status', 'type', 'assigned_to'] as $column) {
            if (is_string($filters[$column] ?? null)) {
                $query->where($column, $filters[$column]);
            }
        }

        $severity = $filters['severity'] ?? $filters['priority'] ?? null;
        if (is_string($severity)) {
            $query->where('severity', $severity);
        }

        $this->applyPeriod($query, $filters);

        if (is_numeric($filters['min_amount'] ?? null)) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (is_numeric($filters['max_amount'] ?? null)) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        if (is_string($filters['search'] ?? null) && $filters['search'] !== '') {
            $term = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('case_number', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? null, self::SORTABLE, true) ? (string) $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function getCaseStatistics(array $filters = []): array
    {
        $base = FraudCase::query();
        $this->applyPeriod($base, $filters);

        $byStatus = [];
        foreach (self::STATUSES as $status) {
            $byStatus[$status] = (clone $base)->where('status', $status)->count();
        }

        $bySeverity = [];
        foreach (self::SEVERITIES as $severity) {
            $bySeverity[$severity] = (clone $base)->where('severity', $severity)->count();
        }

        $resolved = (clone $base)->whereNotNull('resolved_at')->get(['detected_at', 'created_at', 'resolved_at']);
        $hours = $resolved->map(fn (FraudCase $case): int => (int) ($case->detected_at ?? $case->created_at)?->diffInHours($case->resolved_at));

        return [
            'total_cases'      => (clone $base)->count(),
            'by_status'        => $byStatus,
            'by_severity'      => $bySeverity,
            'financial_impact' => [
                'confirmed_amount' => (string) (clone $base)->where('status', FraudCase::STATUS_CONFIRMED)->sum('amount'),
                'total_amount'     => (string) (clone $base)->sum('amount'),
            ],
            'performance' => [
                'average_resolution_hours' => $hours->isEmpty() ? null : round((float) $hours->avg(), 2),
                'cases_per_investigator'   => (clone $base)->whereNotNull('assigned_to')
                    ->toBase()
                    ->selectRaw('assigned_to, COUNT(*) as case_count')
                    ->groupBy('assigned_to')
                    ->pluck('case_count', 'assigned_to')
                    ->all(),
            ],
        ];
    }

    /**
     * Raise the severity one level (critical stays critical) and log why.
     */
    public function escalateCase(FraudCase $case, string $reason): FraudCase
    {
        $severity = match ($case->severity) {
            FraudCase::SEVERITY_LOW    => FraudCase::SEVERITY_MEDIUM,
            FraudCase::SEVERITY_MEDIUM => FraudCase::SEVERITY_HIGH,
            default                    => FraudCase::SEVERITY_CRITICAL,
        };

        $from = $case->severity;
        $case->update(['severity' => $severity]);
        $case->recordAction('escalated', ['from' => $from, 'to' => $severity, 'reason' => $reason, 'by' => $this->actorName()]);

        event(new FraudCaseUpdated($case));

        return $case;
    }

    /**
     * Cases about the same account, newest first, and a log entry that links
     * them.
     *
     * @return Collection<int, FraudCase>
     */
    public function linkSimilarCases(FraudCase $case): Collection
    {
        if ($case->subject_account_uuid === null) {
            return new Collection();
        }

        $similar = FraudCase::query()
            ->whereKeyNot($case->getKey())
            ->where('subject_account_uuid', $case->subject_account_uuid)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($similar->isNotEmpty()) {
            $case->recordAction('linked_similar_cases', ['case_ids' => $similar->modelKeys()]);
        }

        return $similar;
    }

    /**
     * FC-YYYYMM-NNNNNN, counting up within the month.
     */
    protected function generateCaseNumber(): string
    {
        $prefix = 'FC-' . now()->format('Ym') . '-';

        $last = FraudCase::query()
            ->where('case_number', 'like', $prefix . '%')
            ->orderByDesc('case_number')
            ->value('case_number');

        $next = is_string($last) ? ((int) substr($last, -6)) + 1 : 1;

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Critical at a score of 90 or when any rule fired, then high, medium
     * and low at 70 and 50 (the rule the service was designed with).
     */
    protected function severityFor(FraudScore $fraudScore): string
    {
        $score = (float) $fraudScore->total_score;

        return match (true) {
            $score >= 90 || ! empty($fraudScore->triggered_rules) => FraudCase::SEVERITY_CRITICAL,
            $score >= 70                                          => FraudCase::SEVERITY_HIGH,
            $score >= 50                                          => FraudCase::SEVERITY_MEDIUM,
            default                                               => FraudCase::SEVERITY_LOW,
        };
    }

    /**
     * @return list<mixed>
     */
    protected function riskFactors(FraudScore $fraudScore): array
    {
        $factors = [];
        foreach ([$fraudScore->behavioral_factors, $fraudScore->device_factors, $fraudScore->ml_explanation] as $source) {
            if (is_array($source) && is_array($source['risk_factors'] ?? null)) {
                $factors = array_merge($factors, $source['risk_factors']);
            }
        }

        return array_values(array_unique($factors, SORT_REGULAR));
    }

    /**
     * Transaction amounts are kept in minor units; the case stores them in
     * the asset's own units (two decimals when the asset is unknown).
     *
     * @return numeric-string|null
     */
    private function majorUnits(mixed $minor, ?string $currency): ?string
    {
        if (! is_int($minor) && ! (is_string($minor) && ctype_digit($minor))) {
            return null;
        }

        $precision = $currency !== null ? Asset::query()->where('code', $currency)->value('precision') : null;
        $precision = is_int($precision) ? $precision : 2;

        return bcdiv((string) $minor, bcpow('10', (string) $precision), $precision);
    }

    /**
     * @param Builder<FraudCase> $query
     * @param array<string, mixed> $filters
     */
    private function applyPeriod(Builder $query, array $filters): void
    {
        if (is_string($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (is_string($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
    }

    private function flagAccountOwner(FraudCase $case): void
    {
        if ($case->subject_account_uuid === null) {
            return;
        }

        $ownerUuid = Account::query()->where('uuid', $case->subject_account_uuid)->value('user_uuid');
        if (is_string($ownerUuid)) {
            User::query()->where('uuid', $ownerUuid)->update(['risk_rating' => 'high']);
        }
    }

    private function assertStatus(string $status): string
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unknown fraud case status '{$status}'.");
        }

        return $status;
    }

    private function assertSeverity(string $severity): string
    {
        if (! in_array($severity, self::SEVERITIES, true)) {
            throw new InvalidArgumentException("Unknown fraud case severity '{$severity}'.");
        }

        return $severity;
    }

    private function actorName(): string
    {
        $user = auth()->user();

        return $user instanceof User ? (string) $user->name : 'system';
    }
}
