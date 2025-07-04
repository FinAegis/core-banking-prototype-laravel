<?php

namespace App\Domain\Wallet\Contracts;

interface AssetIntegrationServiceInterface
{
    /**
     * Register a new blockchain asset
     *
     * @param array $assetData
     * @return array
     */
    public function registerAsset(array $assetData): array;

    /**
     * Update asset configuration
     *
     * @param string $assetCode
     * @param array $config
     * @return array
     */
    public function updateAssetConfig(string $assetCode, array $config): array;

    /**
     * Enable/disable asset
     *
     * @param string $assetCode
     * @param bool $enabled
     * @return void
     */
    public function toggleAsset(string $assetCode, bool $enabled): void;

    /**
     * Get asset integration status
     *
     * @param string $assetCode
     * @return array
     */
    public function getAssetStatus(string $assetCode): array;

    /**
     * Sync asset metadata from blockchain
     *
     * @param string $assetCode
     * @return array
     */
    public function syncAssetMetadata(string $assetCode): array;

    /**
     * Get supported asset types
     *
     * @return array
     */
    public function getSupportedAssetTypes(): array;

    /**
     * Validate asset configuration
     *
     * @param array $assetData
     * @return array
     */
    public function validateAssetConfig(array $assetData): array;

    /**
     * Get asset integration requirements
     *
     * @param string $assetType
     * @param string $blockchain
     * @return array
     */
    public function getIntegrationRequirements(string $assetType, string $blockchain): array;
}