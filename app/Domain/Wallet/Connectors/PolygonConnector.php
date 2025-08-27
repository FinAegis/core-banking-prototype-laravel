<?php

namespace App\Domain\Wallet\Connectors;

use App\Domain\Wallet\ValueObjects\AddressData;
use Exception;

class PolygonConnector extends EthereumConnector
{
    protected string $chainId = '137'; // Polygon Mainnet

    protected string $rpcUrl;

    public function __construct(array $config = [])
    {
        $rpcUrl = $config['rpc_url'] ?? 'https://polygon-rpc.com';
        $chainId = $config['chain_id'] ?? '137';

        parent::__construct($rpcUrl, $chainId);

        // Override with Polygon-specific settings
        $this->rpcUrl = $rpcUrl;
        $this->chainId = $chainId;
    }

    public function generateAddress(string $publicKey): AddressData
    {
        // Address generation is the same as Ethereum
        $addressData = parent::generateAddress($publicKey);

        // Update chain to polygon
        return new AddressData(
            address: $addressData->address,
            publicKey: $addressData->publicKey,
            chain: 'polygon',
            metadata: array_merge(
                $addressData->metadata,
                [
                    'chain'   => 'polygon',
                    'chainId' => $this->chainId,
                ]
            )
        );
    }

    // public function prepareTransaction(string $from, string $to, string $amount): array
    // {
    //     $transaction = parent::prepareTransaction($from, $to, $amount);

    //     // Update chain ID for Polygon
    //     $transaction['chainId'] = $this->chainId;

    //     // Polygon typically has lower gas prices
    //     $transaction['gasPrice'] = $this->getPolygonGasPrice();

    //     return $transaction;
    // }

    protected function getPolygonGasPrice(): string
    {
        try {
            $gasPrice = null;

            $this->web3->eth->gasPrice(
                function ($err, $price) use (&$gasPrice) {
                    if ($err !== null) {
                        throw new Exception('Failed to get gas price: ' . $err->getMessage());
                    }
                    $gasPrice = $price;
                }
            );

            // Add 20% buffer for Polygon's dynamic fees
            $bufferedPrice = gmp_add(
                gmp_init($gasPrice->toString()),
                gmp_div(gmp_init($gasPrice->toString()), 5)
            );

            return gmp_strval($bufferedPrice);
        } catch (Exception $e) {
            // Fallback to default Polygon gas price (30 Gwei)
            return '30000000000';
        }
    }

    public function getCurrentBlockNumber(): int
    {
        $blockNumber = null;

        $this->web3->eth->blockNumber(
            function ($err, $number) use (&$blockNumber) {
                if ($err !== null) {
                    throw new Exception('Failed to get block number: ' . $err->getMessage());
                }
                $blockNumber = hexdec($number->toString());
            }
        );

        return $blockNumber;
    }
}
