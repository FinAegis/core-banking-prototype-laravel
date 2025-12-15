<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Services\AgentPaymentIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class AgentPaymentIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentPaymentIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AgentPaymentIntegrationService();
    }

    public function test_get_default_currency_returns_config_value(): void
    {
        config(['agent_protocol.wallet.default_currency' => 'EUR']);

        $currency = $this->service->getDefaultCurrency();

        $this->assertEquals('EUR', $currency);
    }

    public function test_get_default_currency_returns_usd_when_not_configured(): void
    {
        // Remove the config key entirely to test fallback
        config(['agent_protocol.wallet' => []]);

        // Create a new service instance to get fresh config
        $service = new AgentPaymentIntegrationService();
        $currency = $service->getDefaultCurrency();

        $this->assertEquals('USD', $currency);
    }

    public function test_calculate_integration_fee_returns_exempt_for_small_amounts(): void
    {
        config([
            'agent_protocol.fees.exemption_threshold' => 1.0,
            'agent_protocol.fees.integration_rate'    => 0.025,
        ]);

        $service = new AgentPaymentIntegrationService();

        // Use reflection to test private method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('calculateIntegrationFee');
        $method->setAccessible(true);

        $result = $method->invoke($service, 0.50, 'funding');

        $this->assertEquals(0.0, $result['amount']);
        $this->assertEquals('exempt', $result['type']);
        $this->assertEquals(0.0, $result['rate']);
    }

    public function test_calculate_integration_fee_returns_none_when_rate_is_zero(): void
    {
        config([
            'agent_protocol.fees.exemption_threshold' => 1.0,
            'agent_protocol.fees.integration_rate'    => 0.0,
        ]);

        $service = new AgentPaymentIntegrationService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('calculateIntegrationFee');
        $method->setAccessible(true);

        $result = $method->invoke($service, 100.00, 'withdrawal');

        $this->assertEquals(0.0, $result['amount']);
        $this->assertEquals('none', $result['type']);
        $this->assertEquals(0.0, $result['rate']);
    }

    public function test_calculate_integration_fee_applies_minimum_fee(): void
    {
        config([
            'agent_protocol.fees.exemption_threshold' => 1.0,
            'agent_protocol.fees.integration_rate'    => 0.01,
            'agent_protocol.fees.minimum_fee'         => 1.00,
            'agent_protocol.fees.maximum_fee'         => 100.00,
        ]);

        $service = new AgentPaymentIntegrationService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('calculateIntegrationFee');
        $method->setAccessible(true);

        // 10 * 0.01 = 0.10, but minimum is 1.00
        $result = $method->invoke($service, 10.00, 'funding');

        $this->assertEquals(1.00, $result['amount']);
        $this->assertEquals('percentage', $result['type']);
        $this->assertEquals(0.01, $result['rate']);
    }

    public function test_calculate_integration_fee_applies_maximum_fee(): void
    {
        config([
            'agent_protocol.fees.exemption_threshold' => 1.0,
            'agent_protocol.fees.integration_rate'    => 0.10, // 10%
            'agent_protocol.fees.minimum_fee'         => 1.00,
            'agent_protocol.fees.maximum_fee'         => 50.00,
        ]);

        $service = new AgentPaymentIntegrationService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('calculateIntegrationFee');
        $method->setAccessible(true);

        // 10000 * 0.10 = 1000, but maximum is 50.00
        $result = $method->invoke($service, 10000.00, 'withdrawal');

        $this->assertEquals(50.00, $result['amount']);
        $this->assertEquals('percentage', $result['type']);
        $this->assertEquals(0.10, $result['rate']);
    }

    public function test_calculate_integration_fee_calculates_percentage_correctly(): void
    {
        config([
            'agent_protocol.fees.exemption_threshold' => 1.0,
            'agent_protocol.fees.integration_rate'    => 0.025, // 2.5%
            'agent_protocol.fees.minimum_fee'         => 0.50,
            'agent_protocol.fees.maximum_fee'         => 100.00,
        ]);

        $service = new AgentPaymentIntegrationService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('calculateIntegrationFee');
        $method->setAccessible(true);

        // 200 * 0.025 = 5.00
        $result = $method->invoke($service, 200.00, 'funding');

        $this->assertEquals(5.00, $result['amount']);
        $this->assertEquals('percentage', $result['type']);
        $this->assertEquals(0.025, $result['rate']);
    }

    public function test_service_can_be_instantiated_without_exchange_service(): void
    {
        $service = new AgentPaymentIntegrationService();

        $this->assertInstanceOf(AgentPaymentIntegrationService::class, $service);
    }

    public function test_get_linked_main_account_returns_null_for_nonexistent_agent(): void
    {
        $result = $this->service->getLinkedMainAccount('did:nonexistent:agent');

        $this->assertNull($result);
    }

    public function test_get_integration_transaction_history_returns_empty_for_nonexistent_agent(): void
    {
        $result = $this->service->getIntegrationTransactionHistory('did:nonexistent:agent');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_link_main_account_returns_false_for_nonexistent_wallet(): void
    {
        $result = $this->service->linkMainAccount('nonexistent-wallet', 'nonexistent-account');

        $this->assertFalse($result);
    }
}
