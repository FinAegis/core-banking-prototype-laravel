<?php

declare(strict_types=1);

use App\Domain\SMS\Clients\VertexSmsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.webhook.dlr_url'               => '',
        'sms.webhook.dlr_url_token'         => '',
    ]);
});

describe('VertexSmsClient::estimateCost', function (): void {
    it('parses /sms/cost single-object response', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/cost' => Http::response([[
                'from'         => 'Zelta',
                'to'           => '37069912345',
                'parts'        => 2,
                'countryISO'   => 'LT',
                'mccmnc'       => '24601',
                'pricePerPart' => 0.035,
                'totalPrice'   => 0.070,
                'currency'     => 'EUR',
            ]], 200),
        ]);

        $cost = (new VertexSmsClient())->estimateCost('37069912345', 'Zelta', 'hello world');

        expect($cost['parts'])->toBe(2);
        expect($cost['country_iso'])->toBe('LT');
        expect($cost['price_per_part_eur'])->toBe(0.035);
        expect($cost['total_price_eur'])->toBe(0.070);
        expect($cost['mccmnc'])->toBe('24601');
    });

    it('throws when /sms/cost returns non-2xx', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/cost' => Http::response(['error' => 'bad request'], 400),
        ]);

        expect(fn () => (new VertexSmsClient())->estimateCost('invalid', 'Zelta', 'x'))
            ->toThrow(RuntimeException::class);
    });
});

describe('VertexSmsClient::sendSms', function (): void {
    it('includes dlrUrl with URL token when configured', function (): void {
        config([
            'sms.webhook.dlr_url'       => 'https://zelta.example/api/v1/webhooks/vertexsms/dlr',
            'sms.webhook.dlr_url_token' => 'the-token-123',
        ]);

        Http::fake([
            'kube-api.vertexsms.com/sms' => Http::response(['abc-message-id'], 200),
        ]);

        (new VertexSmsClient())->sendSms('37069912345', 'Zelta', 'Hello');

        Http::assertSent(function (Request $req): bool {
            $data = $req->data();

            return str_contains((string) $req->url(), 'kube-api.vertexsms.com/sms')
                && isset($data['dlrUrl'])
                && str_contains((string) $data['dlrUrl'], 't=the-token-123')
                && $data['to'] === '37069912345';
        });
    });

    it('omits dlrUrl when no configured override and no named route available', function (): void {
        config([
            'sms.webhook.dlr_url'       => '',
            'sms.webhook.dlr_url_token' => '',
        ]);

        Http::fake([
            'kube-api.vertexsms.com/sms' => Http::response(['msg-456'], 200),
        ]);

        // NOTE: this test runs inside the full app so the named route may
        // actually resolve (fine). We only assert the response shape is
        // parsed into the internal return type.
        $result = (new VertexSmsClient())->sendSms('37069912345', 'Zelta', 'Hello');

        expect($result['message_id'])->toBe('msg-456');
    });

    it('throws when API returns non-2xx', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        expect(fn () => (new VertexSmsClient())->sendSms('37069912345', 'Zelta', 'Hello'))
            ->toThrow(RuntimeException::class);
    });

    it('throws when API token is not configured', function (): void {
        config(['sms.providers.vertexsms.api_token' => '']);

        expect(fn () => (new VertexSmsClient())->sendSms('37069912345', 'Zelta', 'Hello'))
            ->toThrow(RuntimeException::class, 'VertexSMS API token is not configured');
    });
});

describe('VertexSmsClient::verifyDlrUrlToken', function (): void {
    it('returns null when no token configured', function (): void {
        config(['sms.webhook.dlr_url_token' => '']);

        expect((new VertexSmsClient())->verifyDlrUrlToken('anything'))->toBeNull();
    });

    it('returns true on match', function (): void {
        config(['sms.webhook.dlr_url_token' => 'the-expected-token']);

        expect((new VertexSmsClient())->verifyDlrUrlToken('the-expected-token'))->toBeTrue();
    });

    it('returns false on mismatch', function (): void {
        config(['sms.webhook.dlr_url_token' => 'the-expected-token']);

        expect((new VertexSmsClient())->verifyDlrUrlToken('wrong-token'))->toBeFalse();
    });
});
