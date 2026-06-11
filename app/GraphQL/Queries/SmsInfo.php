<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

final class SmsInfo
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{provider: string, enabled: bool, test_mode: bool, networks: array<string>}
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        return [
            'provider'  => (string) config('sms.default_provider', 'mock'),
            'enabled'   => (bool) config('sms.enabled', false),
            'test_mode' => (bool) config('sms.defaults.test_mode', false),
            // Payment rails accepted for paid SMS sends (x402 / MPP).
            'networks' => array_keys((array) config('sms.providers', [])),
        ];
    }
}
