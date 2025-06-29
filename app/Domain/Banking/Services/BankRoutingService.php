<?php

declare(strict_types=1);

namespace App\Domain\Banking\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class BankRoutingService
{
    private BankHealthMonitor $healthMonitor;
    
    public function __construct(BankHealthMonitor $healthMonitor)
    {
        $this->healthMonitor = $healthMonitor;
    }
    
    /**
     * Determine optimal transfer type between banks
     */
    public function determineTransferType(
        string $fromBank,
        string $toBank,
        string $currency,
        float $amount
    ): string {
        // Same bank transfers
        if ($fromBank === $toBank) {
            return 'INTERNAL';
        }
        
        // EUR transfers within EU
        if ($currency === 'EUR' && $this->areBothEuropean($fromBank, $toBank)) {
            // Use instant SEPA for small amounts
            if ($amount <= 15000) {
                return 'SEPA_INSTANT';
            }
            return 'SEPA';
        }
        
        // Default to SWIFT for international
        return 'SWIFT';
    }
    
    /**
     * Get optimal bank for a specific operation
     */
    public function getOptimalBank(
        User $user,
        string $currency,
        float $amount,
        string $transferType,
        Collection $userConnections
    ): string {
        $scores = [];
        
        foreach ($userConnections as $connection) {
            if (!$connection->isActive()) {
                continue;
            }
            
            $score = $this->calculateBankScore(
                $connection->bankCode,
                $currency,
                $amount,
                $transferType
            );
            
            $scores[$connection->bankCode] = $score;
        }
        
        // Return bank with highest score
        arsort($scores);
        return array_key_first($scores);
    }
    
    /**
     * Calculate bank score for routing decision
     */
    private function calculateBankScore(
        string $bankCode,
        string $currency,
        float $amount,
        string $transferType
    ): float {
        $score = 0;
        
        // Health score (0-40 points)
        $health = $this->healthMonitor->checkHealth($bankCode);
        if ($health['status'] === 'healthy') {
            $score += 40;
            
            // Response time bonus (0-10 points)
            if (isset($health['response_time_ms'])) {
                $responseScore = max(0, 10 - ($health['response_time_ms'] / 100));
                $score += $responseScore;
            }
        }
        
        // Capability score (0-30 points)
        if (isset($health['capabilities'])) {
            $capabilities = $health['capabilities'];
            
            // Currency support
            if (in_array($currency, $capabilities['supported_currencies'] ?? [])) {
                $score += 10;
            }
            
            // Transfer type support
            if (in_array($transferType, $capabilities['supported_transfer_types'] ?? [])) {
                $score += 10;
            }
            
            // Instant transfer capability
            if ($transferType === 'SEPA_INSTANT' && ($capabilities['supports_instant_transfers'] ?? false)) {
                $score += 10;
            }
        }
        
        // Fee score (0-20 points)
        $feeScore = $this->calculateFeeScore($bankCode, $transferType, $currency, $amount);
        $score += $feeScore;
        
        // Uptime score (0-10 points)
        $uptime = $this->healthMonitor->getUptimePercentage($bankCode, 24);
        $score += ($uptime / 100) * 10;
        
        return $score;
    }
    
    /**
     * Calculate fee score for a bank
     */
    private function calculateFeeScore(
        string $bankCode,
        string $transferType,
        string $currency,
        float $amount
    ): float {
        // This would be calculated based on actual fee structures
        // For now, return a mock score
        $baseFees = [
            'PAYSERA' => ['SEPA' => 1, 'SWIFT' => 15, 'INTERNAL' => 0],
            'REVOLUT' => ['SEPA' => 0, 'SWIFT' => 5, 'INTERNAL' => 0],
            'WISE' => ['SEPA' => 0.5, 'SWIFT' => 4, 'INTERNAL' => 0],
            'DEUTSCHE' => ['SEPA' => 5, 'SWIFT' => 25, 'INTERNAL' => 0],
            'SANTANDER' => ['SEPA' => 3, 'SWIFT' => 20, 'INTERNAL' => 0],
        ];
        
        $fee = $baseFees[$bankCode][$transferType] ?? 10;
        
        // Lower fees = higher score
        if ($fee === 0) {
            return 20;
        } elseif ($fee <= 1) {
            return 15;
        } elseif ($fee <= 5) {
            return 10;
        } elseif ($fee <= 10) {
            return 5;
        }
        
        return 0;
    }
    
    /**
     * Check if both banks are European
     */
    private function areBothEuropean(string $bank1, string $bank2): bool
    {
        $europeanBanks = ['PAYSERA', 'REVOLUT', 'WISE', 'DEUTSCHE', 'SANTANDER'];
        
        return in_array($bank1, $europeanBanks) && in_array($bank2, $europeanBanks);
    }
    
    /**
     * Get recommended banks for a user based on their needs
     */
    public function getRecommendedBanks(User $user, array $requirements): array
    {
        $allBanks = $this->healthMonitor->checkAllBanks();
        $recommendations = [];
        
        foreach ($allBanks as $bankCode => $health) {
            if ($health['status'] !== 'healthy') {
                continue;
            }
            
            $score = 0;
            $reasons = [];
            
            // Check currency requirements
            if (isset($requirements['currencies'])) {
                $supported = array_intersect(
                    $requirements['currencies'],
                    $health['supported_currencies'] ?? []
                );
                
                if (count($supported) === count($requirements['currencies'])) {
                    $score += 20;
                    $reasons[] = 'Supports all required currencies';
                }
            }
            
            // Check feature requirements
            if (isset($requirements['features'])) {
                $capabilities = $health['capabilities'] ?? [];
                
                foreach ($requirements['features'] as $feature) {
                    if ($this->bankSupportsFeature($capabilities, $feature)) {
                        $score += 10;
                        $reasons[] = "Supports {$feature}";
                    }
                }
            }
            
            // Check country requirements
            if (isset($requirements['countries'])) {
                $availableCountries = $health['capabilities']['available_countries'] ?? [];
                $supported = array_intersect($requirements['countries'], $availableCountries);
                
                if (!empty($supported)) {
                    $score += 15;
                    $reasons[] = 'Available in required countries';
                }
            }
            
            if ($score > 0) {
                $recommendations[] = [
                    'bank_code' => $bankCode,
                    'score' => $score,
                    'reasons' => $reasons,
                    'health' => $health,
                ];
            }
        }
        
        // Sort by score
        usort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $recommendations;
    }
    
    /**
     * Check if bank supports a specific feature
     */
    private function bankSupportsFeature(array $capabilities, string $feature): bool
    {
        $featureMap = [
            'instant_transfers' => 'supports_instant_transfers',
            'multi_currency' => 'supports_multi_currency',
            'virtual_accounts' => 'supports_virtual_accounts',
            'bulk_transfers' => 'supports_bulk_transfers',
            'webhooks' => 'supports_webhooks',
            'cards' => 'supports_card_issuance',
        ];
        
        $capabilityKey = $featureMap[$feature] ?? null;
        
        if ($capabilityKey) {
            return $capabilities[$capabilityKey] ?? false;
        }
        
        return in_array($feature, $capabilities['features'] ?? []);
    }
}