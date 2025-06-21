<?php

declare(strict_types=1);

use App\Domain\Exchange\Providers\MockExchangeRateProvider;
use App\Domain\Exchange\ValueObjects\ExchangeRateQuote;

it('can get exchange rate', function () {
    $provider = new MockExchangeRateProvider(['name' => 'Test Mock Provider']);
    
    $quote = $provider->getRate('USD', 'EUR');
    
    expect($quote)->toBeInstanceOf(ExchangeRateQuote::class);
    expect($quote->fromCurrency)->toBe('USD');
    expect($quote->toCurrency)->toBe('EUR');
    expect($quote->rate)->toBeGreaterThan(0);
    expect($quote->bid)->toBeLessThan($quote->rate);
    expect($quote->ask)->toBeGreaterThan($quote->rate);
    expect($quote->provider)->toBe('Test Mock Provider');
});

it('can get inverse rate', function () {
    $provider = new MockExchangeRateProvider([]);
    
    // Get EUR/USD when USD/EUR is defined
    $quote = $provider->getRate('EUR', 'USD');
    
    expect($quote->rate)->toBeGreaterThan(1.0);
    expect($quote->rate)->toBeLessThan(2.0);
});

it('returns 1.0 for same currency', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $quote = $provider->getRate('USD', 'USD');
    
    expect($quote->rate)->toBe(1.0);
    expect($quote->bid)->toBeLessThan(1.0);
    expect($quote->ask)->toBeGreaterThan(1.0);
});

it('throws exception for unsupported pair', function () {
    $provider = new MockExchangeRateProvider([]);
    
    expect(fn() => $provider->getRate('XXX', 'YYY'))
        ->toThrow(\App\Domain\Exchange\Exceptions\RateProviderException::class);
});

it('can get multiple rates', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $rates = $provider->getRates(['USD/EUR', 'EUR/GBP', 'BTC/USD']);
    
    expect($rates)->toHaveCount(3);
    expect($rates)->toHaveKeys(['USD/EUR', 'EUR/GBP', 'BTC/USD']);
    expect($rates['USD/EUR'])->toBeInstanceOf(ExchangeRateQuote::class);
});

it('can get all rates for base currency', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $rates = $provider->getAllRatesForBase('USD');
    
    expect($rates)->toBeArray();
    expect(count($rates))->toBeGreaterThan(3);
    expect(array_keys($rates)[0])->toStartWith('USD/');
});

it('has correct capabilities', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $capabilities = $provider->getCapabilities();
    
    expect($capabilities->supportsRealtime)->toBeTrue();
    expect($capabilities->supportsHistorical)->toBeFalse();
    expect($capabilities->supportsBidAsk)->toBeTrue();
    expect($capabilities->supportsVolume)->toBeTrue();
    expect($capabilities->supportsBulkQueries)->toBeTrue();
    expect($capabilities->requiresAuthentication)->toBeFalse();
    expect($capabilities->rateLimitPerMinute)->toBe(1000);
});

it('returns supported currencies', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $currencies = $provider->getSupportedCurrencies();
    
    expect($currencies)->toContain('USD');
    expect($currencies)->toContain('EUR');
    expect($currencies)->toContain('BTC');
    expect($currencies)->toContain('ETH');
});

it('can check if pair is supported', function () {
    $provider = new MockExchangeRateProvider([]);
    
    expect($provider->supportsPair('USD', 'EUR'))->toBeTrue();
    expect($provider->supportsPair('BTC', 'USD'))->toBeTrue();
    expect($provider->supportsPair('XXX', 'YYY'))->toBeFalse();
});

it('can set custom mock rate', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $provider->setMockRate('TEST', 'USD', 123.45);
    
    $quote = $provider->getRate('TEST', 'USD');
    
    expect($quote->rate)->toBeGreaterThan(123.0);
    expect($quote->rate)->toBeLessThan(124.0); // Allow for variance
});

it('includes metadata in quote', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $quote = $provider->getRate('USD', 'EUR');
    
    expect($quote->metadata)->toHaveKey('source');
    expect($quote->metadata['source'])->toBe('mock');
    expect($quote->metadata)->toHaveKey('mock_base_rate');
});

it('provides volume and change data', function () {
    $provider = new MockExchangeRateProvider([]);
    
    $quote = $provider->getRate('BTC', 'USD');
    
    expect($quote->volume24h)->toBeGreaterThan(0);
    expect($quote->change24h)->toBeGreaterThanOrEqual(-0.05);
    expect($quote->change24h)->toBeLessThanOrEqual(0.05);
});