<?php

declare(strict_types=1);

namespace App\Infrastructure\Lepo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Lepo Finance public API v2 (company API key in `x-api-key`).
 *
 * Every user-scoped call names the Lepo user id explicitly. Reads are retried
 * once on a connection error; writes are never retried here, because the
 * conversion and card-load endpoints take no idempotency key — callers decide
 * after reading provider state. Request and response bodies are never logged:
 * user endpoints carry personal data, card endpoints carry card secrets, which
 * are stripped before a card leaves this class.
 */
final class LepoClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
    ) {
    }

    public static function fromConfig(): self
    {
        $cfg = (array) config('cardissuance.issuers.lepo', []);

        return new self(
            baseUrl: rtrim((string) ($cfg['base_url'] ?? ''), '/'),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            timeoutSeconds: (int) ($cfg['timeout_seconds'] ?? 15),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createUser(array $payload): array
    {
        return $this->write('POST', '/users', [], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(string $userId): array
    {
        return $this->read('/users/{id}', ['id' => $userId]);
    }

    /**
     * The Lepo user created with our external id, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findUserByExternalId(string $externalUserId): ?array
    {
        $list = $this->read('/users', [], ['externalUserId' => $externalUserId, 'pageSize' => 1]);
        $users = $list['users'] ?? [];

        if (! is_array($users) || ! isset($users[0]) || ! is_array($users[0])) {
            return null;
        }

        /** @var array<string, mixed> $user */
        $user = $users[0];

        return ($user['externalUserId'] ?? null) === $externalUserId ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getKycDetailedStatus(string $userId): array
    {
        return $this->read('/users/{id}/kyc/detailed-status', ['id' => $userId]);
    }

    /**
     * @param  array<string, mixed>  $questionnaire
     * @return array<string, mixed>
     */
    public function createSumsubAccessToken(string $userId, array $questionnaire): array
    {
        return $this->write('POST', '/users/{id}/kyc/sumsub-access-token', ['id' => $userId], $questionnaire);
    }

    /**
     * @param  array<string, mixed>  $questionnaire
     * @return array<string, mixed>
     */
    public function createSumsubKycUrl(string $userId, array $questionnaire): array
    {
        return $this->write('POST', '/users/{id}/sumsub/kyc-url', ['id' => $userId], $questionnaire);
    }

    /**
     * Standard Occupational Classification codes Lepo accepts as `occupation`.
     *
     * @return array<mixed>
     */
    public function getOccupationCodes(): array
    {
        return $this->read('/users/occupation-codes');
    }

    /**
     * Crypto transfers of a user (deposits to their addresses among them),
     * newest first. Filters: transactionHash, startTime/endTime (ms), limit.
     *
     * @param  array<string, scalar>  $filters
     * @return array<string, mixed>
     */
    public function getCryptoTransactions(string $userId, array $filters = []): array
    {
        return $this->read('/transfers/crypto-transactions', [], ['userId' => $userId] + $filters);
    }

    /**
     * One internal transfer (for example a crypto-to-quantum conversion) with
     * its status: PENDING, PROCESSING, COMPLETED, FAILED or CANCELLED.
     *
     * @return array<string, mixed>
     */
    public function getTransfer(string $transferId): array
    {
        return $this->read('/transfers/{id}', ['id' => $transferId]);
    }

    /**
     * Opens the user's funding (Interlace "Quantum") account.
     *
     * @return array<string, mixed>
     */
    public function createFundingAccount(string $userId): array
    {
        // `accountAlreadyExists: true` arrives with `success: false` and is
        // not a failure: the account is there (`interlaceAccountId`).
        return $this->send(
            $this->http(),
            'POST',
            '/users/create-interlace-account/{id}',
            ['id' => $userId],
            [],
            [],
            static fn (array $data): bool => ($data['accountAlreadyExists'] ?? false) === true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getCryptoAddresses(string $userId): array
    {
        return $this->read('/users/{id}/crypto-addresses', ['id' => $userId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAssets(string $userId): array
    {
        return $this->read('/users/{id}/assets', ['id' => $userId]);
    }

    /**
     * Converts a crypto balance into the user's USD funding balance.
     *
     * @param  numeric-string  $amount
     * @return array<string, mixed>
     */
    public function convertCryptoToUsd(string $userId, string $sourceCurrency, string $amount): array
    {
        return $this->write('POST', '/transfers/crypto-to-quantum-transfer', [], [
            'sourceCurrency'      => $sourceCurrency,
            'destinationCurrency' => 'USD',
            'amount'              => $amount,
        ], ['userId' => $userId]);
    }

    /**
     * @param  array<string, scalar>  $query  userId and amount
     * @return array<string, mixed>
     */
    public function estimateCardTopup(array $query): array
    {
        return $this->read('/cards/quantum-topup/estimate', [], $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCard(array $payload): array
    {
        return $this->redactCard($this->write('POST', '/cards', [], $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function listCards(string $userId): array
    {
        $list = $this->read('/cards', [], ['userId' => $userId]);

        if (isset($list['cards']) && is_array($list['cards'])) {
            $list['cards'] = array_map(
                fn (mixed $card): mixed => is_array($card) ? $this->redactCard($card) : $card,
                $list['cards'],
            );
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCard(string $cardId, string $userId): array
    {
        return $this->redactCard($this->read('/cards/{id}', ['id' => $cardId], ['userId' => $userId]));
    }

    /**
     * @return array<string, mixed>
     */
    public function freezeCard(string $cardId, string $userId): array
    {
        return $this->write('POST', '/cards/{id}/freeze', ['id' => $cardId], [], ['userId' => $userId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function enableCard(string $cardId, string $userId): array
    {
        return $this->write('POST', '/cards/{id}/enable', ['id' => $cardId], [], ['userId' => $userId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteCard(string $cardId, string $userId): array
    {
        return $this->write('DELETE', '/cards/{id}', ['id' => $cardId], [], ['userId' => $userId]);
    }

    /**
     * Loads the card from the user's funding balance.
     *
     * @param  numeric-string  $value
     * @return array<string, mixed>
     */
    public function topUpCard(string $cardId, string $value, ?string $token = null): array
    {
        $body = ['cardId' => $cardId, 'value' => $value];
        if ($token !== null) {
            $body['token'] = $token;
        }

        return $this->write('POST', '/cards/topup', [], $body);
    }

    /**
     * Pages start at 0 (Lepo documentation).
     *
     * @return array<string, mixed>
     */
    public function getCardTransactions(string $cardId, string $userId, int $page = 0, int $limit = 20): array
    {
        return $this->read('/cards/{id}/transactions', ['id' => $cardId], [
            'userId' => $userId,
            'page'   => $page,
            'limit'  => $limit,
        ]);
    }

    /**
     * Short-lived URL of Lepo's secure card-details view (iframe). The card
     * number and CVV never pass through this platform.
     *
     * @return array<string, mixed>
     */
    public function getCardWidget(string $cardId, string $userId): array
    {
        return $this->read('/cards/{id}/widget', ['id' => $cardId], ['userId' => $userId]);
    }

    /**
     * @param  array<string, string>  $params  path placeholders
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function read(string $template, array $params = [], array $query = []): array
    {
        $request = $this->http()->retry(2, 200, throw: false);

        return $this->send($request, 'GET', $template, $params, [], $query);
    }

    /**
     * @param  array<string, string>  $params
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function write(string $method, string $template, array $params = [], array $body = [], array $query = []): array
    {
        return $this->send($this->http(), $method, $template, $params, $body, $query);
    }

    /**
     * @param  array<string, string>  $params
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function send(PendingRequest $request, string $method, string $template, array $params, array $body, array $query, ?callable $acceptFailureBody = null): array
    {
        $path = $template;
        foreach ($params as $name => $value) {
            $path = str_replace('{' . $name . '}', rawurlencode($value), $path);
        }

        $url = $this->baseUrl . $path . ($query === [] ? '' : '?' . http_build_query($query));

        try {
            /** @var Response $response */
            $response = match ($method) {
                'GET'    => $request->get($url),
                'POST'   => $request->post($url, $body),
                'PUT'    => $request->put($url, $body),
                'DELETE' => $request->delete($url),
                default  => throw LepoApiException::transport($template, 0),
            };
        } catch (ConnectionException) {
            throw LepoApiException::transport($template, 0);
        }

        return $this->decode($response, $template, $acceptFailureBody);
    }

    /**
     * @param  (callable(array<mixed>): bool)|null  $acceptFailureBody
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $template, ?callable $acceptFailureBody = null): array
    {
        $status = $response->status();
        $json = $response->json();
        $data = is_array($json) ? $json : [];

        if ($status < 400 && ! is_array($json) && trim($response->body()) !== '') {
            // A proxy or error page answering 200 must never read as success,
            // least of all for conversions and card loads.
            throw LepoApiException::unexpectedResponse($template, $status);
        }

        if ($status >= 500) {
            throw LepoApiException::transport($template, $status);
        }

        if ($status >= 400) {
            throw LepoApiException::business($template, $this->errorCode($data), $status);
        }

        if (($data['success'] ?? null) === false && ! ($acceptFailureBody !== null && $acceptFailureBody($data))) {
            throw LepoApiException::business($template, $this->errorCode($data), $status);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function errorCode(array $data): ?string
    {
        $code = $data['errorCode'] ?? $data['code'] ?? null;

        return is_scalar($code) ? (string) $code : null;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function redactCard(array $card): array
    {
        /** @var array<string, mixed> $redacted */
        $redacted = LepoRedactor::cardSecrets($card);

        return $redacted;
    }

    private function http(): PendingRequest
    {
        if ($this->apiKey === '') {
            throw LepoApiException::notConfigured();
        }

        return Http::acceptJson()
            ->asJson()
            ->withHeaders(['x-api-key' => $this->apiKey])
            ->timeout($this->timeoutSeconds);
    }
}
