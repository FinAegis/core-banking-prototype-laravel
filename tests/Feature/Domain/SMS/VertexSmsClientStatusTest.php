<?php

declare(strict_types=1);

use App\Domain\SMS\Clients\VertexSmsClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.webhook.dlr_url'               => '',
        'sms.webhook.dlr_url_token'         => '',
    ]);
});

describe('VertexSmsClient::getMessageStatus', function (): void {
    it('returns parsed status for a delivered message', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/status/12345*' => Http::response([
                'id'     => 12345,
                'status' => 1,
                'error'  => 0,
            ], 200),
        ]);

        $result = (new VertexSmsClient())->getMessageStatus('12345');

        assert(is_array($result));
        expect($result['id'])->toBe('12345');
        expect($result['status'])->toBe(1);
        expect($result['error'])->toBe(0);
    });

    it('returns parsed status for a failed message', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/status/67890*' => Http::response([
                'id'     => 67890,
                'status' => 2,
                'error'  => 24,
            ], 200),
        ]);

        $result = (new VertexSmsClient())->getMessageStatus('67890');

        assert(is_array($result));
        expect($result['status'])->toBe(2);
        expect($result['error'])->toBe(24);
    });

    it('returns null on non-2xx response', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/status/99999*' => Http::response('Not found', 404),
        ]);

        $result = (new VertexSmsClient())->getMessageStatus('99999');

        expect($result)->toBeNull();
    });
});
