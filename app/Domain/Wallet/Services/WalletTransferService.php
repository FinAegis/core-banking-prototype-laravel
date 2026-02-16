<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\MobilePayment\Enums\PaymentAsset;
use App\Domain\MobilePayment\Enums\PaymentNetwork;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WalletTransferService
{
    /**
     * Validate a blockchain address for a specific network.
     *
     * @return array{valid: bool, network: string, address: string, address_type: string|null, error: string|null}
     */
    public function validateAddress(string $address, string $network): array
    {
        $paymentNetwork = PaymentNetwork::tryFrom($network);

        if (! $paymentNetwork) {
            return [
                'valid'        => false,
                'network'      => $network,
                'address'      => $address,
                'address_type' => null,
                'error'        => 'Unsupported network. Supported: ' . implode(', ', PaymentNetwork::values()),
            ];
        }

        $pattern = $paymentNetwork->addressPattern();
        $isValid = (bool) preg_match($pattern, $address);

        return [
            'valid'        => $isValid,
            'network'      => $paymentNetwork->value,
            'address'      => $address,
            'address_type' => $isValid ? $this->detectAddressType($address, $paymentNetwork) : null,
            'error'        => $isValid ? null : "Invalid {$paymentNetwork->label()} address format.",
        ];
    }

    /**
     * Resolve an ENS/SNS name to a blockchain address.
     *
     * @return array{resolved: bool, name: string, address: string|null, network: string, error: string|null}
     */
    public function resolveName(string $name, string $network): array
    {
        $paymentNetwork = PaymentNetwork::tryFrom($network);

        if (! $paymentNetwork) {
            return [
                'resolved' => false,
                'name'     => $name,
                'address'  => null,
                'network'  => $network,
                'error'    => 'Unsupported network. Supported: ' . implode(', ', PaymentNetwork::values()),
            ];
        }

        return $this->performNameResolution($name, $paymentNetwork);
    }

    /**
     * Get a fee quote for a wallet-to-wallet transfer.
     *
     * @return array{network: string, asset: string, estimated_fee: string, fee_currency: string, estimated_time_seconds: int, exchange_rate_usd: string}
     */
    public function getTransferQuote(string $network, string $asset, string $amount): array
    {
        $paymentNetwork = PaymentNetwork::from($network);
        $paymentAsset = PaymentAsset::from($asset);

        $gasCostUsd = $paymentNetwork->averageGasCostUsd();

        return [
            'network'                => $paymentNetwork->value,
            'asset'                  => $paymentAsset->value,
            'amount'                 => $amount,
            'estimated_fee'          => number_format($gasCostUsd, 6),
            'fee_currency'           => 'USD',
            'estimated_time_seconds' => $this->estimateTransferTime($paymentNetwork),
            'exchange_rate_usd'      => '1.000000',
        ];
    }

    /**
     * Get a transaction quote with recipient address validation.
     *
     * @return array{quote_id: string, to: string, amount: string, asset: string, network: string, fee: string, total: string, expires_at: string}
     */
    public function getTransactionQuote(string $to, string $network, string $asset, string $amount): array
    {
        $paymentNetwork = PaymentNetwork::from($network);
        $paymentAsset = PaymentAsset::from($asset);

        // Validate recipient address
        $validation = $this->validateAddress($to, $network);
        if (! $validation['valid']) {
            throw new InvalidArgumentException('Invalid recipient address: ' . ($validation['error'] ?? 'unknown error'));
        }

        $gasCostUsd = $paymentNetwork->averageGasCostUsd();
        $total = (float) $amount + $gasCostUsd;

        return [
            'quote_id'   => 'quote_' . Str::random(20),
            'to'         => $to,
            'amount'     => $amount,
            'asset'      => $paymentAsset->value,
            'network'    => $paymentNetwork->value,
            'fee'        => number_format($gasCostUsd, 6),
            'total'      => number_format($total, 6),
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ];
    }

    /**
     * Detect address type based on format heuristics.
     */
    private function detectAddressType(string $address, PaymentNetwork $network): string
    {
        return match ($network) {
            PaymentNetwork::SOLANA => strlen($address) >= 40 ? 'program' : 'wallet',
            PaymentNetwork::TRON   => str_starts_with($address, 'T') ? 'base58' : 'hex',
        };
    }

    /**
     * Perform name resolution for the given name and network.
     *
     * In production, this would call external resolvers (SNS for Solana, etc.).
     * Currently returns a stub response indicating resolution is not yet available.
     *
     * @return array{resolved: bool, name: string, address: string|null, network: string, error: string|null}
     */
    private function performNameResolution(string $name, PaymentNetwork $network): array
    {
        // SNS (.sol) resolution for Solana
        if ($network === PaymentNetwork::SOLANA && str_ends_with($name, '.sol')) {
            return [
                'resolved' => false,
                'name'     => $name,
                'address'  => null,
                'network'  => $network->value,
                'error'    => 'SNS resolution requires external resolver integration. Please use a wallet address directly.',
            ];
        }

        // ENS (.eth) - not natively supported on Solana/Tron
        if (str_ends_with($name, '.eth')) {
            return [
                'resolved' => false,
                'name'     => $name,
                'address'  => null,
                'network'  => $network->value,
                'error'    => 'ENS names (.eth) are not supported on ' . $network->label() . '. Please use a wallet address directly.',
            ];
        }

        return [
            'resolved' => false,
            'name'     => $name,
            'address'  => null,
            'network'  => $network->value,
            'error'    => 'Name resolution is not available for this format. Please use a wallet address directly.',
        ];
    }

    /**
     * Estimate transfer confirmation time in seconds.
     */
    private function estimateTransferTime(PaymentNetwork $network): int
    {
        return match ($network) {
            PaymentNetwork::SOLANA => 5,
            PaymentNetwork::TRON   => 30,
        };
    }
}
