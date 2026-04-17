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
    it('parses /sms/cost single-object response and splits mccmnc', function (): void {
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
        expect($cost['mcc'])->toBe('246');
        expect($cost['mnc'])->toBe('01');
        expect($cost['price_per_part_eur'])->toBeString();
        expect((float) $cost['price_per_part_eur'])->toBe(0.035);
        expect((float) $cost['total_price_eur'])->toBe(0.070);
    });

    it('handles missing mccmnc gracefully', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/cost' => Http::response([[
                'parts'        => 1,
                'countryISO'   => 'DE',
                'pricePerPart' => 0.04,
                'totalPrice'   => 0.04,
                'currency'     => 'EUR',
            ]], 200),
        ]);

        $cost = (new VertexSmsClient())->estimateCost('491701234567', 'Zelta', 'x');

        expect($cost['mcc'])->toBeNull();
        expect($cost['mnc'])->toBeNull();
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

    it('returns the message id from the API response', function (): void {
        config([
            'sms.webhook.dlr_url'       => '',
            'sms.webhook.dlr_url_token' => '',
        ]);

        Http::fake([
            'kube-api.vertexsms.com/sms' => Http::response(['msg-456'], 200),
        ]);

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

describe('VertexSmsClient::sendSms throttle', function (): void {
    it('acquires a cache lock before sending', function (): void {
        config([
            'sms.defaults.send_interval_ms' => 1000,
            'cache.default'                 => 'array',
        ]);

        Http::fake([
            'kube-api.vertexsms.com/sms' => Http::response(['throttle-msg-1'], 200),
        ]);

        $client = new VertexSmsClient();
        $result = $client->sendSms('37069912345', 'Zelta', 'Throttle test');

        expect($result['message_id'])->toBe('throttle-msg-1');
        Http::assertSentCount(1);
    });
});
