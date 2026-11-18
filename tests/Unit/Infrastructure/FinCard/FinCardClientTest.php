<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\FinCard;

use App\Infrastructure\FinCard\FinCardApiException;
use App\Infrastructure\FinCard\FinCardClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinCardClientTest extends TestCase
{
    private FinCardClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        $this->client = new FinCardClient(
            baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
            tenantId: 'tenant-1',
            orgId: 'ORG',
            userId: 'USER',
            username: 'svc',
            password: 'pw',
            forwardedFrom: 'zelta',
        );
    }

    /**
     * @return array<string, \GuzzleHttp\Promise\PromiseInterface>
     */
    private function loginFake(string $token = 'jwt-abc', int $expiresIn = 3600): array
    {
        return [
            'sandbox.finhub.cloud/api/v2.1/admin/*' => Http::response([
                'success' => true,
                'code'    => 0,
                'msg'     => 'ok',
                'data'    => ['accessToken' => $token, 'expiresIn' => $expiresIn],
            ]),
        ];
    }

    public function test_login_caches_token_and_reuses_it(): void
    {
        Http::fake($this->loginFake('jwt-xyz'));

        $this->assertSame('jwt-xyz', $this->client->getAccessToken());
        // Second call must hit the cache, not the network.
        $this->assertSame('jwt-xyz', $this->client->getAccessToken());

        $adminCalls = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/admin/'))
            ->count();
        $this->assertSame(1, $adminCalls, 'login should be performed once and then cached');
    }

    public function test_get_card_types_sends_bearer_and_context_headers(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/cardTypes' => Http::response([
                'success' => true,
                'code'    => 0,
                'msg'     => 'ok',
                'data'    => ['records' => [['cardTypeId' => 111001]]],
            ]),
        ]));

        $result = $this->client->getCardTypes();

        $this->assertTrue($result['success']);
        $this->assertSame(111001, $result['data']['records'][0]['cardTypeId']);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/card/v2/cardTypes')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer jwt-abc')
                && $request->hasHeader('X-Tenant-ID', 'tenant-1')
                && $request->hasHeader('X-Forwarded-From', 'zelta')
                && $request->hasHeader('platform', 'web')
                && $request->hasHeader('deviceId', 'zelta-server');
        });
    }

    public function test_context_overrides_are_forwarded(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/*' => Http::response([
                'success' => true, 'code' => 0, 'msg' => 'ok', 'data' => [],
            ]),
        ]));

        $this->client->getOccupations([
            'platform'      => 'ios',
            'device_id'     => 'device-42',
            'forwarded_for' => '203.0.113.9',
        ]);

        Http::assertSent(fn ($request): bool => $request->hasHeader('platform', 'ios')
            && $request->hasHeader('deviceId', 'device-42')
            && $request->hasHeader('X-Forwarded-For', '203.0.113.9'));
    }

    public function test_rpc_throws_on_business_failure(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/*' => Http::response([
                'success' => false,
                'code'    => 2003,
                'msg'     => 'invalid region',
                'data'    => null,
            ]),
        ]));

        try {
            $this->client->getCities('ZZ');
            $this->fail('expected FinCardApiException');
        } catch (FinCardApiException $e) {
            $this->assertSame(2003, $e->apiCode);
            $this->assertStringContainsString('invalid region', $e->getMessage());
        }
    }

    public function test_rpc_throws_on_transport_failure(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/*' => Http::response(['x' => 'y'], 503),
        ]));

        try {
            $this->client->getCoins();
            $this->fail('expected FinCardApiException');
        } catch (FinCardApiException $e) {
            $this->assertSame(503, $e->httpStatus);
        }
    }

    public function test_rpc_reauthenticates_once_on_401(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/*' => Http::sequence()
                ->push(['success' => false, 'msg' => 'expired'], 401)
                ->push(['success' => true, 'code' => 0, 'msg' => 'ok', 'data' => ['records' => []]], 200),
        ]));

        $result = $this->client->getCardTypes();

        $this->assertTrue($result['success']);

        // A fresh login happens on the initial miss AND after the 401 forgets it.
        $adminCalls = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/admin/'))
            ->count();
        $this->assertSame(2, $adminCalls);
    }

    public function test_empty_body_is_serialized_as_json_object(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/*' => Http::response([
                'success' => true, 'code' => 0, 'msg' => 'ok', 'data' => [],
            ]),
        ]));

        $this->client->getCoins();

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/wallet/v2/coins')) {
                return false;
            }

            // FinCard rejects a bare `[]`; an empty payload must be `{}`.
            return $request->body() === '{}';
        });
    }

    public function test_login_without_token_in_response_throws(): void
    {
        Http::fake([
            'sandbox.finhub.cloud/api/v2.1/admin/*' => Http::response([
                'success' => true, 'code' => 0, 'msg' => 'ok', 'data' => [],
            ]),
        ]);

        $this->expectException(FinCardApiException::class);
        $this->client->login();
    }
}
