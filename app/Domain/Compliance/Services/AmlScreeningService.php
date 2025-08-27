<?php

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Aggregates\AmlScreeningAggregate;
use App\Domain\Compliance\Events\ScreeningCompleted;
use App\Domain\Compliance\Events\ScreeningMatchFound;
use App\Domain\Compliance\Models\AmlScreening;
use App\Domain\Compliance\Models\CustomerRiskProfile;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AmlScreeningService
{
    private array $sanctionsLists = [
        'OFAC' => 'https://api.ofac.treasury.gov/v1/',
        'EU'   => 'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList_1_1/content',
        'UN'   => 'https://api.un.org/sc/suborg/en/sanctions/un-sc-consolidated-list',
    ];

    private string $provider = 'Internal';

    /**
     * Perform comprehensive screening.
     */
    public function performComprehensiveScreening($entity, array $parameters = []): AmlScreening
    {
        return DB::transaction(
            function () use ($entity, $parameters) {
                $aggregateId = (string) Str::uuid();
                $screeningNumber = $this->generateUniqueScreeningNumber();
                $searchParams = $this->buildSearchParameters($entity, $parameters);
                $startTime = microtime(true);

                // Create aggregate and start screening
                $aggregate = AmlScreeningAggregate::retrieve($aggregateId)
                    ->startScreening(
                        entityId: $entity->id,
                        entityType: get_class($entity),
                        screeningNumber: $screeningNumber,
                        type: AmlScreening::TYPE_COMPREHENSIVE,
                        provider: $this->provider,
                        searchParameters: $searchParams,
                        providerReference: null
                    );

                try {
                    // Perform all screening types with the search parameters
                    $sanctionsResults = $this->performSanctionsCheck($searchParams);
                    $pepResults = $this->performPEPCheck($searchParams);
                    $adverseMediaResults = $this->performAdverseMediaCheck($searchParams);
                    $otherResults = [];

                    // Calculate overall risk
                    $overallRisk = $this->calculateOverallRisk($sanctionsResults, $pepResults, $adverseMediaResults);
                    $totalMatches = $this->countTotalMatches($sanctionsResults, $pepResults, $adverseMediaResults);

                    // Collect all lists checked
                    $listsChecked = array_merge(
                        $sanctionsResults['lists_checked'] ?? [],
                        ['PEP Database'],
                        ['Adverse Media Sources']
                    );

                    // Record results
                    $aggregate->recordResults(
                        sanctionsResults: $sanctionsResults,
                        pepResults: $pepResults,
                        adverseMediaResults: $adverseMediaResults,
                        otherResults: $otherResults,
                        totalMatches: $totalMatches,
                        overallRisk: $overallRisk,
                        listsChecked: $listsChecked,
                        apiResponse: null
                    );

                    // Complete screening
                    $processingTime = microtime(true) - $startTime;
                    $aggregate->completeScreening(
                        finalStatus: 'completed',
                        processingTime: $processingTime
                    );

                    // Persist aggregate
                    $aggregate->persist();

                    // Create a temporary screening object for immediate use
                    // Note: The actual database record will be created by a projector
                    $screening = new AmlScreening([
                        'entity_id'             => $entity->id,
                        'entity_type'           => get_class($entity),
                        'screening_number'      => $screeningNumber,
                        'type'                  => AmlScreening::TYPE_COMPREHENSIVE,
                        'status'                => 'completed',
                        'provider'              => $this->provider,
                        'search_parameters'     => $searchParams,
                        'sanctions_results'     => $sanctionsResults,
                        'pep_results'           => $pepResults,
                        'adverse_media_results' => $adverseMediaResults,
                        'other_results'         => $otherResults,
                        'total_matches'         => $totalMatches,
                        'overall_risk'          => $overallRisk,
                        'lists_checked'         => $listsChecked,
                        'started_at'            => now(),
                        'completed_at'          => now(),
                        'processing_time'       => $processingTime,
                        'aggregate_root_uuid'   => $aggregateId,
                    ]);

                    // Set the ID to use the aggregate ID
                    $screening->id = $aggregateId;

                    event(new ScreeningCompleted($screening));

                    if ($totalMatches > 0) {
                        event(new ScreeningMatchFound($screening));
                    }

                    return $screening;
                } catch (Exception $e) {
                    // Record failure
                    $processingTime = microtime(true) - $startTime;
                    $aggregate->completeScreening(
                        finalStatus: 'failed',
                        processingTime: $processingTime
                    );
                    // Persist aggregate
                    $aggregate->persist();

                    Log::error('AML screening failed', [
                        'aggregate_id' => $aggregateId,
                        'error'        => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }
        );
    }

    /**
     * Perform sanctions screening check.
     */
    public function performSanctionsCheck(array $searchParams): array
    {
        $results = [
            'matches'       => [],
            'lists_checked' => [],
            'total_matches' => 0,
        ];

        // Check OFAC SDN List
        $ofacResults = $this->checkOFACList($searchParams);
        if (! empty($ofacResults)) {
            $results['matches']['OFAC'] = $ofacResults;
            $results['total_matches'] += count($ofacResults);
        }
        $results['lists_checked'][] = 'OFAC';

        // Check EU Sanctions
        $euResults = $this->checkEUSanctions($searchParams);
        if (! empty($euResults)) {
            $results['matches']['EU'] = $euResults;
            $results['total_matches'] += count($euResults);
        }
        $results['lists_checked'][] = 'EU';

        // Check UN Sanctions
        $unResults = $this->checkUNSanctions($searchParams);
        if (! empty($unResults)) {
            $results['matches']['UN'] = $unResults;
            $results['total_matches'] += count($unResults);
        }
        $results['lists_checked'][] = 'UN';

        return $results;
    }

    /**
     * Perform PEP screening check.
     */
    public function performPEPCheck(array $searchParams): array
    {

        // In production, this would integrate with a PEP database provider
        // For now, simulate PEP checking
        $results = [
            'is_pep'        => false,
            'pep_type'      => null,
            'position'      => null,
            'country'       => null,
            'since_date'    => null,
            'matches'       => [],
            'total_matches' => 0,
        ];

        // Check against known PEP indicators
        $name = $searchParams['name'] ?? '';
        $country = $searchParams['country'] ?? '';

        // Simulate PEP database check
        if ($this->checkPEPDatabase($name, $country)) {
            $results['is_pep'] = true;
            $results['pep_type'] = 'domestic';
            $results['position'] = 'Former Government Official';
            $results['country'] = $country;
            $results['since_date'] = now()->subYears(2)->toDateString();
            $results['matches'][] = [
                'name'        => $name,
                'match_score' => 95,
                'source'      => 'PEP Database',
            ];
            $results['total_matches'] = 1;
        }

        return $results;
    }

    /**
     * Perform adverse media screening check.
     */
    public function performAdverseMediaCheck(array $searchParams): array
    {

        // In production, this would integrate with news aggregation services
        $results = [
            'has_adverse_media'   => false,
            'total_articles'      => 0,
            'serious_allegations' => 0,
            'categories'          => [],
            'articles'            => [],
            'total_matches'       => 0,
        ];

        // Simulate adverse media check
        $adverseMedia = $this->searchAdverseMedia($searchParams['name'] ?? '');

        if (! empty($adverseMedia)) {
            $results['has_adverse_media'] = true;
            $results['total_articles'] = count($adverseMedia);
            $results['total_matches'] = count($adverseMedia);
            $results['articles'] = $adverseMedia;

            foreach ($adverseMedia as $article) {
                if ($article['severity'] === 'high') {
                    $results['serious_allegations']++;
                }
                $results['categories'][] = $article['category'];
            }
            $results['categories'] = array_unique($results['categories']);
        }

        return $results;
    }

    /**
     * Build search parameters from entity.
     */
    protected function buildSearchParameters($entity, array $additionalParams = []): array
    {
        $params = [];

        if ($entity instanceof User) {
            $params = [
                'name'          => $entity->name,
                'date_of_birth' => $entity->date_of_birth?->toDateString(),
                'country'       => $entity->country ?? 'US',
                'id_number'     => $entity->id_number ?? null,
            ];
        } elseif ($entity instanceof FinancialInstitutionApplication) {
            $params = [
                'name'                => $entity->institution_name,
                'legal_name'          => $entity->legal_name,
                'country'             => $entity->country,
                'registration_number' => $entity->registration_number,
            ];
        }

        return array_merge($params, $additionalParams);
    }

    /**
     * Check OFAC SDN List.
     */
    protected function checkOFACList(array $searchParams): array
    {
        // In production, this would make actual API calls to OFAC
        // For demonstration, simulate the check

        $matches = [];
        $name = $searchParams['name'] ?? '';

        // Simulate OFAC API call
        try {
            // In real implementation:
            // $response = Http::get($this->sanctionsLists['OFAC'] . 'search', [
            //     'name' => $name,
            //     'fuzzy' => true,
            // ]);

            // Simulated response
            if (str_contains(strtolower($name), 'test') || str_contains(strtolower($name), 'sanctioned')) {
                $matches[] = [
                    'sdn_id'      => '12345',
                    'name'        => $name,
                    'match_score' => 92,
                    'type'        => 'Individual',
                    'program'     => 'CYBER2',
                    'remarks'     => 'Added to SDN list on 2023-01-01',
                ];
            }
        } catch (Exception $e) {
            Log::error('OFAC check failed', ['error' => $e->getMessage()]);
        }

        return $matches;
    }

    /**
     * Check EU Sanctions.
     */
    protected function checkEUSanctions(array $searchParams): array
    {
        // Simulate EU sanctions check
        return [];
    }

    /**
     * Check UN Sanctions.
     */
    protected function checkUNSanctions(array $searchParams): array
    {
        // Simulate UN sanctions check
        return [];
    }

    /**
     * Check PEP Database.
     */
    protected function checkPEPDatabase(string $name, string $country): bool
    {
        // In production, integrate with PEP database providers like:
        // - Dow Jones Risk & Compliance
        // - Refinitiv World-Check
        // - LexisNexis

        // Simulate PEP check
        $pepKeywords = ['minister', 'senator', 'governor', 'official'];
        foreach ($pepKeywords as $keyword) {
            if (str_contains(strtolower($name), $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search adverse media.
     */
    protected function searchAdverseMedia(string $name): array
    {
        // In production, integrate with news aggregation services
        // Simulate adverse media search

        $articles = [];

        if (str_contains(strtolower($name), 'fraud') || str_contains(strtolower($name), 'scandal')) {
            $articles[] = [
                'title'    => "Investigation into {$name} financial practices",
                'source'   => 'Financial Times',
                'date'     => now()->subMonths(2)->toDateString(),
                'category' => 'Financial Crime',
                'severity' => 'high',
                'url'      => 'https://example.com/article1',
            ];
        }

        return $articles;
    }

    /**
     * Calculate overall risk.
     */
    protected function calculateOverallRisk(array $sanctions, array $pep, array $adverseMedia): string
    {
        // Critical if sanctioned
        if (($sanctions['total_matches'] ?? 0) > 0) {
            return AmlScreening::RISK_CRITICAL;
        }

        // High if PEP or serious adverse media
        if (($pep['is_pep'] ?? false) || ($adverseMedia['serious_allegations'] ?? 0) > 0) {
            return AmlScreening::RISK_HIGH;
        }

        // Medium if any adverse media
        if ($adverseMedia['has_adverse_media'] ?? false) {
            return AmlScreening::RISK_MEDIUM;
        }

        // Low if clean
        return AmlScreening::RISK_LOW;
    }

    /**
     * Count total matches across all screening types.
     */
    protected function countTotalMatches(array $sanctions, array $pep, array $adverseMedia): int
    {
        $count = $sanctions['total_matches'] ?? 0;
        $count += $pep['total_matches'] ?? 0;
        $count += $adverseMedia['total_matches'] ?? 0;

        return $count;
    }

    /**
     * Review screening results using aggregate.
     */
    public function reviewScreening(AmlScreening $screening, string $decision, string $notes, User $reviewer): void
    {
        // Find the aggregate ID - it might be stored as the screening ID or in a separate field
        $aggregateId = $screening->aggregate_root_uuid ?? $screening->id;

        try {
            // Use aggregate for event-sourced screenings
            $aggregate = AmlScreeningAggregate::retrieve($aggregateId);
            $aggregate->reviewScreening(
                reviewedBy: $reviewer->id,
                decision: $decision,
                notes: $notes
            );
            $aggregate->persist();
        } catch (Exception $e) {
            // Fallback for legacy screenings without aggregate
            Log::warning('Failed to retrieve aggregate for screening review', [
                'screening_id' => $screening->id,
                'aggregate_id' => $aggregateId,
                'error'        => $e->getMessage(),
            ]);

            $screening->addReview($decision, $notes, $reviewer);
        }

        // Update risk profile if applicable
        if ($screening->entity_type === User::class) {
            $this->updateCustomerRiskProfile($screening);
        }
    }

    /**
     * Update customer risk profile based on screening.
     */
    protected function updateCustomerRiskProfile(AmlScreening $screening): void
    {
        /** @var \Illuminate\Database\Eloquent\Model|null $profile */
        $profile = CustomerRiskProfile::where('user_id', $screening->entity_id)->first();

        if (! $profile) {
            return;
        }

        $updates = [
            'sanctions_verified_at'    => now(),
            'pep_verified_at'          => now(),
            'adverse_media_checked_at' => now(),
        ];

        if ($screening->sanctions_results['total_matches'] > 0) {
            $updates['is_sanctioned'] = true;
            $updates['sanctions_details'] = $screening->sanctions_results;
        }

        if ($screening->pep_results['is_pep']) {
            $updates['is_pep'] = true;
            $updates['pep_type'] = $screening->pep_results['pep_type'];
            $updates['pep_position'] = $screening->pep_results['position'];
            $updates['pep_details'] = $screening->pep_results;
        }

        if ($screening->adverse_media_results['has_adverse_media']) {
            $updates['has_adverse_media'] = true;
            $updates['adverse_media_details'] = $screening->adverse_media_results;
        }

        $profile->update($updates);
        $profile->updateRiskAssessment();
    }

    /**
     * Generate a unique screening number.
     */
    protected function generateUniqueScreeningNumber(): string
    {
        $year = date('Y');
        $lastScreening = AmlScreening::whereYear('created_at', $year)
            ->orderBy('screening_number', 'desc')
            ->first();

        if ($lastScreening && preg_match('/AML-\d{4}-(\d{5})/', $lastScreening->screening_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('AML-%s-%05d', $year, $nextNumber);
    }

    /**
     * Update match status through aggregate.
     */
    public function updateMatchStatus(
        AmlScreening $screening,
        string $matchId,
        string $action,
        array $details = [],
        ?string $reason = null
    ): void {
        $aggregateId = $screening->aggregate_root_uuid ?? $screening->id;

        try {
            $aggregate = AmlScreeningAggregate::retrieve($aggregateId);
            $aggregate->updateMatchStatus(
                matchId: $matchId,
                action: $action,
                details: $details,
                reason: $reason
            );
            $aggregate->persist();
        } catch (Exception $e) {
            Log::error('Failed to update match status through aggregate', [
                'screening_id' => $screening->id,
                'aggregate_id' => $aggregateId,
                'match_id'     => $matchId,
                'error'        => $e->getMessage(),
            ]);
            throw new RuntimeException('Cannot update match status: ' . $e->getMessage());
        }
    }

    /**
     * Perform sanctions screening (legacy method for backward compatibility).
     */
    public function performSanctionsScreening(AmlScreening $screening): array
    {
        return $this->performSanctionsCheck($screening->search_parameters);
    }

    /**
     * Perform PEP screening (legacy method for backward compatibility).
     */
    public function performPEPScreening(AmlScreening $screening): array
    {
        return $this->performPEPCheck($screening->search_parameters);
    }

    /**
     * Perform adverse media screening (legacy method for backward compatibility).
     */
    public function performAdverseMediaScreening(AmlScreening $screening): array
    {
        return $this->performAdverseMediaCheck($screening->search_parameters);
    }

    /**
     * Perform specific type of screening using aggregate.
     */
    public function performScreeningByType($entity, string $type, array $parameters = []): AmlScreening
    {
        return DB::transaction(
            function () use ($entity, $type, $parameters) {
                $aggregateId = (string) Str::uuid();
                $screeningNumber = $this->generateUniqueScreeningNumber();
                $searchParams = $this->buildSearchParameters($entity, $parameters);
                $startTime = microtime(true);

                // Create aggregate and start screening
                $aggregate = AmlScreeningAggregate::retrieve($aggregateId)
                    ->startScreening(
                        entityId: $entity->id,
                        entityType: get_class($entity),
                        screeningNumber: $screeningNumber,
                        type: $type,
                        provider: $this->provider,
                        searchParameters: $searchParams,
                        providerReference: null
                    );

                try {
                    // Initialize results
                    $sanctionsResults = ['matches' => [], 'lists_checked' => [], 'total_matches' => 0];
                    $pepResults = ['is_pep' => false, 'matches' => [], 'total_matches' => 0];
                    $adverseMediaResults = ['has_adverse_media' => false, 'articles' => [], 'total_matches' => 0];
                    $otherResults = [];
                    $listsChecked = [];

                    // Perform specific screening type
                    switch ($type) {
                        case AmlScreening::TYPE_SANCTIONS:
                            $sanctionsResults = $this->performSanctionsCheck($searchParams);
                            $listsChecked = $sanctionsResults['lists_checked'] ?? [];
                            break;

                        case AmlScreening::TYPE_PEP:
                            $pepResults = $this->performPEPCheck($searchParams);
                            $listsChecked = ['PEP Database'];
                            break;

                        case AmlScreening::TYPE_ADVERSE_MEDIA:
                            $adverseMediaResults = $this->performAdverseMediaCheck($searchParams);
                            $listsChecked = ['Adverse Media Sources'];
                            break;

                        case AmlScreening::TYPE_COMPREHENSIVE:
                            return $this->performComprehensiveScreening($entity, $parameters);

                        default:
                            throw new InvalidArgumentException("Invalid screening type: {$type}");
                    }

                    // Calculate overall risk based on specific type results
                    $overallRisk = $this->calculateOverallRisk($sanctionsResults, $pepResults, $adverseMediaResults);
                    $totalMatches = $this->countTotalMatches($sanctionsResults, $pepResults, $adverseMediaResults);

                    // Record results
                    $aggregate->recordResults(
                        sanctionsResults: $sanctionsResults,
                        pepResults: $pepResults,
                        adverseMediaResults: $adverseMediaResults,
                        otherResults: $otherResults,
                        totalMatches: $totalMatches,
                        overallRisk: $overallRisk,
                        listsChecked: $listsChecked,
                        apiResponse: null
                    );

                    // Complete screening
                    $processingTime = microtime(true) - $startTime;
                    $aggregate->completeScreening(
                        finalStatus: 'completed',
                        processingTime: $processingTime
                    );

                    // Persist aggregate
                    $aggregate->persist();

                    // Create a temporary screening object for immediate use
                    $screening = new AmlScreening([
                        'entity_id'             => $entity->id,
                        'entity_type'           => get_class($entity),
                        'screening_number'      => $screeningNumber,
                        'type'                  => $type,
                        'status'                => 'completed',
                        'provider'              => $this->provider,
                        'search_parameters'     => $searchParams,
                        'sanctions_results'     => $sanctionsResults,
                        'pep_results'           => $pepResults,
                        'adverse_media_results' => $adverseMediaResults,
                        'other_results'         => $otherResults,
                        'total_matches'         => $totalMatches,
                        'overall_risk'          => $overallRisk,
                        'lists_checked'         => $listsChecked,
                        'started_at'            => now(),
                        'completed_at'          => now(),
                        'processing_time'       => $processingTime,
                        'aggregate_root_uuid'   => $aggregateId,
                    ]);

                    // Set the ID to use the aggregate ID
                    $screening->id = $aggregateId;

                    event(new ScreeningCompleted($screening));

                    if ($totalMatches > 0) {
                        event(new ScreeningMatchFound($screening));
                    }

                    return $screening;
                } catch (Exception $e) {
                    // Record failure
                    $processingTime = microtime(true) - $startTime;
                    $aggregate->completeScreening(
                        finalStatus: 'failed',
                        processingTime: $processingTime
                    );
                    // Persist aggregate
                    $aggregate->persist();

                    Log::error('AML screening failed', [
                        'aggregate_id' => $aggregateId,
                        'type'         => $type,
                        'error'        => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }
        );
    }
}
