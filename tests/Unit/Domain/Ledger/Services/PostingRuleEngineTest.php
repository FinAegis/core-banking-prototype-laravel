<?php

declare(strict_types=1);

use App\Domain\Ledger\Services\PostingRuleEngine;

uses(Tests\TestCase::class);

describe('PostingRuleEngine', function (): void {
    it('resolveRules returns empty array for unknown event', function (): void {
        $engine = new PostingRuleEngine();

        $result = $engine->resolveRules('unknown.event.that.does.not.exist', ['amount' => '100.00']);

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    it('resolves event.amount from event data', function (): void {
        // Test the resolution logic via reflection on the private method
        $engine = new PostingRuleEngine();
        $reflection = new ReflectionClass(PostingRuleEngine::class);
        $method = $reflection->getMethod('resolveAmount');

        $result = $method->invoke($engine, 'event.amount', ['amount' => '250.50']);

        expect($result)->toBe('250.50');
    });

    it('resolves event.fee from event data', function (): void {
        $engine = new PostingRuleEngine();
        $reflection = new ReflectionClass(PostingRuleEngine::class);
        $method = $reflection->getMethod('resolveAmount');

        $result = $method->invoke($engine, 'event.fee', ['fee' => '5.00', 'amount' => '100.00']);

        expect($result)->toBe('5.00');
    });

    it('resolves event.interest from event data', function (): void {
        $engine = new PostingRuleEngine();
        $reflection = new ReflectionClass(PostingRuleEngine::class);
        $method = $reflection->getMethod('resolveAmount');

        $result = $method->invoke($engine, 'event.interest', ['interest' => '12.75']);

        expect($result)->toBe('12.75');
    });

    it('handles literal numeric strings', function (): void {
        $engine = new PostingRuleEngine();
        $reflection = new ReflectionClass(PostingRuleEngine::class);
        $method = $reflection->getMethod('resolveAmount');

        $result = $method->invoke($engine, '99.99', []);

        expect($result)->toBe('99.99');
    });

    it('returns zero for unknown expression', function (): void {
        $engine = new PostingRuleEngine();
        $reflection = new ReflectionClass(PostingRuleEngine::class);
        $method = $reflection->getMethod('resolveAmount');

        $result = $method->invoke($engine, 'some_unknown_expression', []);

        expect($result)->toBe('0');
    });

    it('returns zero when event key is missing from data', function (): void {
        $engine = new PostingRuleEngine();
        $reflection = new ReflectionClass(PostingRuleEngine::class);
        $method = $reflection->getMethod('resolveAmount');

        $result = $method->invoke($engine, 'event.missing_key', ['amount' => '100.00']);

        expect($result)->toBe('0');
    });
});
