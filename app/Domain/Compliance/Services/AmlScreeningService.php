<?php

namespace App\Domain\Compliance\Services;

use App\Models\User;
use App\Models\FinancialInstitutionApplication;
use App\Models\AmlScreening;
use App\Models\CustomerRiskProfile;
use App\Domain\Compliance\Events\ScreeningCompleted;
use App\Domain\Compliance\Events\ScreeningMatchFound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AmlScreeningService
{
    private array $sanctionsLists = [
        'OFAC' => 'https://api.ofac.treasury.gov/v1/',
        'EU' => 'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList_1_1/content',
        'UN' => 'https://api.un.org/sc/suborg/en/sanctions/un-sc-consolidated-list',
    ];
    
    /**
     * Perform comprehensive screening
     */
    public function performComprehensiveScreening($entity, array $parameters = []): AmlScreening
    {
        return DB::transaction(function () use ($entity, $parameters) {
            $screening = $this->createScreening($entity, AmlScreening::TYPE_COMPREHENSIVE, $parameters);
            
            try {
                $screening->update(['status' => AmlScreening::STATUS_IN_PROGRESS]);
                
                // Perform all screening types
                $sanctionsResults = $this->performSanctionsScreening($screening);
                $pepResults = $this->performPEPScreening($screening);
                $adverseMediaResults = $this->performAdverseMediaScreening($screening);
                
                // Calculate overall risk
                $overallRisk = $this->calculateOverallRisk($sanctionsResults, $pepResults, $adverseMediaResults);
                
                // Update screening with results
                $screening->update([
                    'sanctions_results' => $sanctionsResults,
                    'pep_results' => $pepResults,
                    'adverse_media_results' => $adverseMediaResults,
                    'overall_risk' => $overallRisk,
                    'total_matches' => $this->countTotalMatches($sanctionsResults, $pepResults, $adverseMediaResults),
                ]);
                
                $screening->markAsCompleted();
                
                event(new ScreeningCompleted($screening));
                
                if ($screening->hasMatches()) {
                    event(new ScreeningMatchFound($screening));
                }
                
                return $screening;
                
            } catch (\Exception $e) {
                $screening->markAsFailed($e->getMessage());
                throw $e;
            }
        });
    }
    
    /**
     * Perform sanctions screening
     */
    public function performSanctionsScreening(AmlScreening $screening): array
    {
        $results = [
            'matches' => [],
            'lists_checked' => [],
            'total_matches' => 0,
        ];
        
        $searchParams = $screening->search_parameters;
        
        // Check OFAC SDN List
        $ofacResults = $this->checkOFACList($searchParams);
        if (!empty($ofacResults)) {
            $results['matches']['OFAC'] = $ofacResults;
            $results['total_matches'] += count($ofacResults);
        }
        $results['lists_checked'][] = 'OFAC';
        
        // Check EU Sanctions
        $euResults = $this->checkEUSanctions($searchParams);
        if (!empty($euResults)) {
            $results['matches']['EU'] = $euResults;
            $results['total_matches'] += count($euResults);
        }
        $results['lists_checked'][] = 'EU';
        
        // Check UN Sanctions
        $unResults = $this->checkUNSanctions($searchParams);
        if (!empty($unResults)) {
            $results['matches']['UN'] = $unResults;
            $results['total_matches'] += count($unResults);
        }
        $results['lists_checked'][] = 'UN';
        
        return $results;
    }
    
    /**
     * Perform PEP screening
     */
    public function performPEPScreening(AmlScreening $screening): array
    {
        $searchParams = $screening->search_parameters;
        
        // In production, this would integrate with a PEP database provider
        // For now, simulate PEP checking
        $results = [
            'is_pep' => false,
            'pep_type' => null,
            'position' => null,
            'country' => null,
            'since_date' => null,
            'matches' => [],
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
                'name' => $name,
                'match_score' => 95,
                'source' => 'PEP Database',
            ];
        }
        
        return $results;
    }
    
    /**
     * Perform adverse media screening
     */
    public function performAdverseMediaScreening(AmlScreening $screening): array
    {
        $searchParams = $screening->search_parameters;
        
        // In production, this would integrate with news aggregation services
        $results = [
            'has_adverse_media' => false,
            'total_articles' => 0,
            'serious_allegations' => 0,
            'categories' => [],
            'articles' => [],
        ];
        
        // Simulate adverse media check
        $adverseMedia = $this->searchAdverseMedia($searchParams['name'] ?? '');
        
        if (!empty($adverseMedia)) {
            $results['has_adverse_media'] = true;
            $results['total_articles'] = count($adverseMedia);
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
     * Create screening record
     */
    protected function createScreening($entity, string $type, array $parameters): AmlScreening
    {
        $entityType = get_class($entity);
        $entityId = $entity->id;
        
        // Build search parameters based on entity type
        $searchParams = $this->buildSearchParameters($entity, $parameters);
        
        return AmlScreening::create([
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'type' => $type,
            'status' => AmlScreening::STATUS_PENDING,
            'search_parameters' => $searchParams,
            'fuzzy_matching' => $parameters['fuzzy_matching'] ?? true,
            'match_threshold' => $parameters['match_threshold'] ?? 85,
            'started_at' => now(),
        ]);
    }
    
    /**
     * Build search parameters from entity
     */
    protected function buildSearchParameters($entity, array $additionalParams = []): array
    {
        $params = [];
        
        if ($entity instanceof User) {
            $params = [
                'name' => $entity->name,
                'date_of_birth' => $entity->date_of_birth?->toDateString(),
                'country' => $entity->country ?? 'US',
                'id_number' => $entity->id_number ?? null,
            ];
        } elseif ($entity instanceof FinancialInstitutionApplication) {
            $params = [
                'name' => $entity->institution_name,
                'legal_name' => $entity->legal_name,
                'country' => $entity->country,
                'registration_number' => $entity->registration_number,
            ];
        }
        
        return array_merge($params, $additionalParams);
    }
    
    /**
     * Check OFAC SDN List
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
                    'sdn_id' => '12345',
                    'name' => $name,
                    'match_score' => 92,
                    'type' => 'Individual',
                    'program' => 'CYBER2',
                    'remarks' => 'Added to SDN list on 2023-01-01',
                ];
            }
        } catch (\Exception $e) {
            Log::error('OFAC check failed', ['error' => $e->getMessage()]);
        }
        
        return $matches;
    }
    
    /**
     * Check EU Sanctions
     */
    protected function checkEUSanctions(array $searchParams): array
    {
        // Simulate EU sanctions check
        return [];
    }
    
    /**
     * Check UN Sanctions
     */
    protected function checkUNSanctions(array $searchParams): array
    {
        // Simulate UN sanctions check
        return [];
    }
    
    /**
     * Check PEP Database
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
     * Search adverse media
     */
    protected function searchAdverseMedia(string $name): array
    {
        // In production, integrate with news aggregation services
        // Simulate adverse media search
        
        $articles = [];
        
        if (str_contains(strtolower($name), 'fraud') || str_contains(strtolower($name), 'scandal')) {
            $articles[] = [
                'title' => "Investigation into {$name} financial practices",
                'source' => 'Financial Times',
                'date' => now()->subMonths(2)->toDateString(),
                'category' => 'Financial Crime',
                'severity' => 'high',
                'url' => 'https://example.com/article1',
            ];
        }
        
        return $articles;
    }
    
    /**
     * Calculate overall risk
     */
    protected function calculateOverallRisk(array $sanctions, array $pep, array $adverseMedia): string
    {
        // Critical if sanctioned
        if ($sanctions['total_matches'] > 0) {
            return AmlScreening::RISK_CRITICAL;
        }
        
        // High if PEP or serious adverse media
        if ($pep['is_pep'] || $adverseMedia['serious_allegations'] > 0) {
            return AmlScreening::RISK_HIGH;
        }
        
        // Medium if any adverse media
        if ($adverseMedia['has_adverse_media']) {
            return AmlScreening::RISK_MEDIUM;
        }
        
        // Low if clean
        return AmlScreening::RISK_LOW;
    }
    
    /**
     * Count total matches across all screening types
     */
    protected function countTotalMatches(array $sanctions, array $pep, array $adverseMedia): int
    {
        $count = $sanctions['total_matches'] ?? 0;
        
        if ($pep['is_pep']) {
            $count++;
        }
        
        if ($adverseMedia['has_adverse_media']) {
            $count += $adverseMedia['total_articles'] ?? 0;
        }
        
        return $count;
    }
    
    /**
     * Review screening results
     */
    public function reviewScreening(AmlScreening $screening, string $decision, string $notes, User $reviewer): void
    {
        $screening->addReview($decision, $notes, $reviewer);
        
        // Update risk profile if applicable
        if ($screening->entity_type === User::class) {
            $this->updateCustomerRiskProfile($screening);
        }
    }
    
    /**
     * Update customer risk profile based on screening
     */
    protected function updateCustomerRiskProfile(AmlScreening $screening): void
    {
        $profile = CustomerRiskProfile::where('user_id', $screening->entity_id)->first();
        
        if (!$profile) {
            return;
        }
        
        $updates = [
            'sanctions_verified_at' => now(),
            'pep_verified_at' => now(),
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
}