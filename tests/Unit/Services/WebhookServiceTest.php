<?php

declare(strict_types=1);
use Tests\UnitTestCase;

uses(UnitTestCase::class);

use App\Services\WebhookService;

describe('WebhookService', function () {
    it('can generate HMAC signature', function () {
        $service = new WebhookService();
        $payload = '{"test":"data"}';
        $secret = 'test-secret';
        
        $signature = $service->generateSignature($payload, $secret);
        
        expect($signature)->toBeString();
        expect($signature)->toStartWith('sha256=');
        
        // Verify the signature is correct
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        expect($signature)->toBe($expectedSignature);
    });
    
    it('generates different signatures for different payloads', function () {
        $service = new WebhookService();
        $secret = 'test-secret';
        
        $signature1 = $service->generateSignature('{"test":"data1"}', $secret);
        $signature2 = $service->generateSignature('{"test":"data2"}', $secret);
        
        expect($signature1)->not->toBe($signature2);
    });
    
    it('generates different signatures for different secrets', function () {
        $service = new WebhookService();
        $payload = '{"test":"data"}';
        
        $signature1 = $service->generateSignature($payload, 'secret1');
        $signature2 = $service->generateSignature($payload, 'secret2');
        
        expect($signature1)->not->toBe($signature2);
    });
    
    it('generates consistent signatures for same input', function () {
        $service = new WebhookService();
        $payload = '{"test":"data"}';
        $secret = 'test-secret';
        
        $signature1 = $service->generateSignature($payload, $secret);
        $signature2 = $service->generateSignature($payload, $secret);
        
        expect($signature1)->toBe($signature2);
    });
});