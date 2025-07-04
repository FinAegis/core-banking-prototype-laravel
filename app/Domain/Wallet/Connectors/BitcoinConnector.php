<?php

namespace App\Domain\Wallet\Connectors;

use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Wallet\ValueObjects\AddressData;
use App\Domain\Wallet\ValueObjects\BalanceData;
use App\Domain\Wallet\ValueObjects\GasEstimate;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionResult;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;
use Illuminate\Support\Facades\Http;

class BitcoinConnector implements BlockchainConnector
{
    private string $network;
    private string $apiUrl;
    private ?string $apiKey;
    
    public function __construct(array $config = [])
    {
        $this->network = $config['network'] ?? 'mainnet';
        $this->apiUrl = $config['api_url'] ?? 'https://api.blockcypher.com/v1/btc/' . $this->network;
        $this->apiKey = $config['api_key'] ?? null;
    }
    
    public function generateAddress(string $publicKey): AddressData
    {
        $network = $this->network === 'mainnet' 
            ? NetworkFactory::bitcoin() 
            : NetworkFactory::bitcoinTestnet();
            
        // Create P2PKH address from public key
        $pubKeyBuf = Buffer::hex($publicKey);
        $pubKey = \BitWasp\Bitcoin\Crypto\EcAdapter\EcSerializer::getSerializer(
            \BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Adapter\EcAdapter::class
        )->getPublicKeySerializer()->parse($pubKeyBuf);
        
        $address = \BitWasp\Bitcoin\Address\PayToPubKeyHashAddress::fromKey($pubKey, $network);
        
        return new AddressData(
            address: $address->getAddress(),
            publicKey: $publicKey,
            chain: 'bitcoin',
            metadata: [
                'type' => 'P2PKH',
                'network' => $this->network,
            ]
        );
    }
    
    public function validateAddress(string $address): bool
    {
        try {
            $network = $this->network === 'mainnet' 
                ? NetworkFactory::bitcoin() 
                : NetworkFactory::bitcoinTestnet();
                
            \BitWasp\Bitcoin\Address\AddressCreator::fromString($address, $network);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getBalance(string $address): string
    {
        $response = Http::get("{$this->apiUrl}/addrs/{$address}/balance");
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch balance');
        }
        
        $data = $response->json();
        return (string) ($data['balance'] ?? 0);
    }
    
    public function getTokenBalance(string $address, string $tokenAddress): string
    {
        // Bitcoin doesn't have tokens like Ethereum
        throw new \Exception('Bitcoin does not support tokens');
    }
    
    public function getTransaction(string $transactionHash): array
    {
        $response = Http::get("{$this->apiUrl}/txs/{$transactionHash}");
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch transaction');
        }
        
        $data = $response->json();
        
        return [
            'hash' => $data['hash'],
            'from' => $data['inputs'][0]['addresses'][0] ?? null,
            'to' => $data['outputs'][0]['addresses'][0] ?? null,
            'value' => (string) ($data['outputs'][0]['value'] ?? 0),
            'confirmations' => $data['confirmations'] ?? 0,
            'blockNumber' => $data['block_height'] ?? null,
            'gasUsed' => null, // Bitcoin uses fees, not gas
            'gasPrice' => null,
            'confirmed' => ($data['confirmations'] ?? 0) > 0,
            'fee' => (string) ($data['fees'] ?? 0),
        ];
    }
    
    public function prepareTransaction(string $from, string $to, string $amount): array
    {
        // Get UTXOs for the address
        $utxos = $this->getUTXOs($from);
        
        // Select UTXOs to cover amount + fee
        $selectedUtxos = [];
        $totalInput = 0;
        $amountSatoshis = (int) $amount;
        $estimatedFee = 10000; // 0.0001 BTC estimated fee
        
        foreach ($utxos as $utxo) {
            $selectedUtxos[] = $utxo;
            $totalInput += $utxo['value'];
            
            if ($totalInput >= $amountSatoshis + $estimatedFee) {
                break;
            }
        }
        
        if ($totalInput < $amountSatoshis + $estimatedFee) {
            throw new \Exception('Insufficient balance for transaction');
        }
        
        $change = $totalInput - $amountSatoshis - $estimatedFee;
        
        return [
            'inputs' => $selectedUtxos,
            'outputs' => [
                ['address' => $to, 'value' => $amountSatoshis],
                ['address' => $from, 'value' => $change], // Change output
            ],
            'fee' => $estimatedFee,
        ];
    }
    
    public function prepareTokenTransfer(
        string $from,
        string $to,
        string $tokenAddress,
        string $amount
    ): array {
        throw new \Exception('Bitcoin does not support tokens');
    }
    
    public function signTransaction(array $transaction, string $privateKey): array
    {
        $network = $this->network === 'mainnet' 
            ? NetworkFactory::bitcoin() 
            : NetworkFactory::bitcoinTestnet();
            
        $ec = Bitcoin::getEcAdapter();
        $privKey = \BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory::fromHex($privateKey, false, $ec);
        
        // Build transaction
        $tx = TransactionFactory::build();
        
        // Add inputs
        foreach ($transaction['inputs'] as $input) {
            $tx->input(
                Buffer::hex($input['txid']),
                $input['vout']
            );
        }
        
        // Add outputs
        foreach ($transaction['outputs'] as $output) {
            if ($output['value'] > 0) {
                $address = \BitWasp\Bitcoin\Address\AddressCreator::fromString(
                    $output['address'],
                    $network
                );
                $tx->payToAddress($output['value'], $address);
            }
        }
        
        $unsigned = $tx->get();
        
        // Sign inputs
        $signer = new Signer($unsigned, $ec);
        foreach ($transaction['inputs'] as $idx => $input) {
            $output = new TransactionOutput(
                $input['value'],
                ScriptFactory::scriptPubKey()->payToPubKeyHash($privKey->getPubKeyHash())
            );
            
            $signer->sign($idx, $privKey, $output);
        }
        
        $signed = $signer->get();
        
        return [
            'hex' => $signed->getHex(),
            'hash' => $signed->getTxId()->getHex(),
        ];
    }
    
    public function broadcastTransaction(array $signedTransaction): string
    {
        $response = Http::post("{$this->apiUrl}/txs/push", [
            'tx' => $signedTransaction['hex'],
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to broadcast transaction: ' . $response->body());
        }
        
        $data = $response->json();
        return $data['tx']['hash'];
    }
    
    public function estimateGas(string $from, string $to, string $amount): string
    {
        // Bitcoin uses fee estimation, not gas
        // Return estimated fee in satoshis
        $feeRate = $this->getEstimatedFeeRate();
        $txSize = 250; // Estimated transaction size in bytes
        
        return (string) ($feeRate * $txSize);
    }
    
    public function getTokenInfo(string $tokenAddress): array
    {
        throw new \Exception('Bitcoin does not support tokens');
    }
    
    public function getCurrentBlockNumber(): int
    {
        $response = Http::get("{$this->apiUrl}");
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch blockchain info');
        }
        
        $data = $response->json();
        return $data['height'] ?? 0;
    }
    
    private function getUTXOs(string $address): array
    {
        $response = Http::get("{$this->apiUrl}/addrs/{$address}?unspentOnly=true&includeScript=true");
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch UTXOs');
        }
        
        $data = $response->json();
        $utxos = [];
        
        foreach ($data['txrefs'] ?? [] as $ref) {
            if (!$ref['spent']) {
                $utxos[] = [
                    'txid' => $ref['tx_hash'],
                    'vout' => $ref['tx_output_n'],
                    'value' => $ref['value'],
                    'script' => $ref['script'] ?? '',
                ];
            }
        }
        
        return $utxos;
    }
    
    private function getEstimatedFeeRate(): int
    {
        // In production, this would fetch current fee rates from the network
        // For now, return a conservative estimate (satoshis per byte)
        return 20;
    }
}