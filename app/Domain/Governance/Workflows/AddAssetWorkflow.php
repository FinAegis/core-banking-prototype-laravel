<?php

declare(strict_types=1);

namespace App\Domain\Governance\Workflows;

use App\Domain\Asset\Models\Asset;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\ValueObjects\PollResult;
use Exception;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class AddAssetWorkflow
{
    #[WorkflowMethod]
    public function execute(Poll $poll, PollResult $result): array
    {
        // Extract asset details from poll metadata or winning option
        $assetData = $this->extractAssetDataFromPoll($poll, $result);

        // Validate asset data
        if (! $this->validateAssetData($assetData)) {
            return [
                'success'   => false,
                'message'   => 'Invalid asset data in poll configuration',
                'poll_uuid' => $poll->uuid,
            ];
        }

        // Check if asset already exists
        if (Asset::where('code', $assetData['code'])->exists()) {
            return [
                'success'   => false,
                'message'   => "Asset {$assetData['code']} already exists",
                'poll_uuid' => $poll->uuid,
            ];
        }

        try {
            // Create the new asset
            $asset = Asset::create(
                [
                    'code'      => $assetData['code'],
                    'name'      => $assetData['name'],
                    'type'      => $assetData['type'],
                    'precision' => $assetData['precision'],
                    'is_active' => true,
                    'metadata'  => array_merge(
                        $assetData['metadata'] ?? [],
                        [
                            'added_via_poll' => $poll->uuid,
                            'poll_result'    => $result->toArray(),
                            'added_at'       => now()->toISOString(),
                        ]
                    ),
                ]
            );

            // Log the governance action
            logger()->info(
                'Asset added via governance poll',
                [
                    'poll_uuid'          => $poll->uuid,
                    'asset_code'         => $asset->code,
                    'winning_option'     => $result->winningOption,
                    'participation_rate' => $result->participationRate,
                ]
            );

            return [
                'success'    => true,
                'message'    => "Asset {$asset->code} successfully added via governance",
                'poll_uuid'  => $poll->uuid,
                'asset_code' => $asset->code,
                'asset_id'   => $asset->id,
            ];
        } catch (Exception $e) {
            logger()->error(
                'Failed to add asset via governance poll',
                [
                    'poll_uuid'  => $poll->uuid,
                    'asset_data' => $assetData,
                    'error'      => $e->getMessage(),
                ]
            );

            return [
                'success'   => false,
                'message'   => 'Failed to add asset: ' . $e->getMessage(),
                'poll_uuid' => $poll->uuid,
            ];
        }
    }

    private function extractAssetDataFromPoll(Poll $poll, PollResult $result): array
    {
        // Try to get asset data from poll metadata first
        if (isset($poll->metadata['asset_data'])) {
            return $poll->metadata['asset_data'];
        }

        // Try to extract from winning option metadata
        $winningOption = null;
        foreach ($poll->options as $option) {
            if ($option['id'] === $result->winningOption) {
                $winningOption = $option;
                break;
            }
        }

        if ($winningOption && isset($winningOption['metadata']['asset_data'])) {
            return $winningOption['metadata']['asset_data'];
        }

        // Fallback: try to parse from poll title/description
        return $this->parseAssetDataFromText($poll->title, $poll->description);
    }

    private function parseAssetDataFromText(string $title, ?string $description): array
    {
        // Simple pattern matching for common asset codes
        $assetPatterns = [
            'JPY'  => ['name' => 'Japanese Yen', 'type' => 'fiat', 'precision' => 2],
            'CAD'  => ['name' => 'Canadian Dollar', 'type' => 'fiat', 'precision' => 2],
            'AUD'  => ['name' => 'Australian Dollar', 'type' => 'fiat', 'precision' => 2],
            'CHF'  => ['name' => 'Swiss Franc', 'type' => 'fiat', 'precision' => 2],
            'SEK'  => ['name' => 'Swedish Krona', 'type' => 'fiat', 'precision' => 2],
            'NOK'  => ['name' => 'Norwegian Krone', 'type' => 'fiat', 'precision' => 2],
            'DKK'  => ['name' => 'Danish Krone', 'type' => 'fiat', 'precision' => 2],
            'ADA'  => ['name' => 'Cardano', 'type' => 'crypto', 'precision' => 8],
            'DOT'  => ['name' => 'Polkadot', 'type' => 'crypto', 'precision' => 8],
            'LINK' => ['name' => 'Chainlink', 'type' => 'crypto', 'precision' => 8],
            'XAG'  => ['name' => 'Silver', 'type' => 'commodity', 'precision' => 6],
            'XPT'  => ['name' => 'Platinum', 'type' => 'commodity', 'precision' => 6],
            'XPD'  => ['name' => 'Palladium', 'type' => 'commodity', 'precision' => 6],
        ];

        $text = $title . ' ' . ($description ?? '');

        foreach ($assetPatterns as $code => $data) {
            if (preg_match('/\b' . preg_quote($code, '/') . '\b/i', $text)) {
                return array_merge(['code' => $code], $data);
            }
        }

        // Default fallback
        return [
            'code'      => 'UNKNOWN',
            'name'      => 'Unknown Asset',
            'type'      => 'fiat',
            'precision' => 2,
        ];
    }

    private function validateAssetData(array $data): bool
    {
        $required = ['code', 'name', 'type', 'precision'];

        foreach ($required as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        // Validate asset code format (2-5 uppercase letters/numbers)
        if (! preg_match('/^[A-Z0-9]{2,5}$/', $data['code'])) {
            return false;
        }

        // Validate asset type
        $validTypes = ['fiat', 'crypto', 'commodity'];
        if (! in_array($data['type'], $validTypes, true)) {
            return false;
        }

        // Validate precision
        if (! is_int($data['precision']) || $data['precision'] < 0 || $data['precision'] > 18) {
            return false;
        }

        return true;
    }
}
