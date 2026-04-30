<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\MCP\Tools\Account\AccountBalanceTool;
use App\Domain\AI\MCP\Tools\Account\CreateAccountTool;
use App\Domain\AI\MCP\Tools\Account\DepositTool;
use App\Domain\AI\MCP\Tools\Account\WithdrawTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentEscrowTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentMandateTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentPaymentTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentReputationTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentVdcTool;
use App\Domain\AI\MCP\Tools\Compliance\AmlScreeningTool;
use App\Domain\AI\MCP\Tools\Compliance\KycTool;
use App\Domain\AI\MCP\Tools\Exchange\LiquidityPoolTool;
use App\Domain\AI\MCP\Tools\Exchange\QuoteTool;
use App\Domain\AI\MCP\Tools\Exchange\TradeTool;
use App\Domain\AI\MCP\Tools\MachinePay\MppDiscoveryTool;
use App\Domain\AI\MCP\Tools\MachinePay\MppPaymentTool;
use App\Domain\AI\MCP\Tools\Payment\PaymentStatusTool;
use App\Domain\AI\MCP\Tools\Payment\TransferTool;
use App\Domain\AI\MCP\Tools\SMS\SmsSendTool;
use App\Domain\AI\MCP\Tools\Transaction\SpendingAnalysisTool;
use App\Domain\AI\MCP\Tools\Transaction\TransactionQueryTool;
use App\Domain\AI\MCP\Tools\VisaCli\VisaCliCardsTool;
use App\Domain\AI\MCP\Tools\VisaCli\VisaCliPaymentTool;
use App\Domain\AI\MCP\Tools\X402\X402PaymentTool;
use App\Domain\MCP\Tools\Ramp\RampStartTool;
use App\Domain\MCP\Tools\Ramp\RampStatusTool;
use Exception;
use Illuminate\Support\ServiceProvider;
use Log;

/**
 * Service Provider for MCP Tools Registration.
 *
 * Registers all available MCP tools with the ToolRegistry
 * for use by the AI framework and MCP server.
 */
class MCPToolServiceProvider extends ServiceProvider
{
    /**
     * All MCP tools to be registered.
     *
     * @var array<class-string>
     */
    protected array $tools = [
        // Account Tools
        AccountBalanceTool::class,
        CreateAccountTool::class,
        DepositTool::class,
        WithdrawTool::class,

        // Payment Tools
        TransferTool::class,
        PaymentStatusTool::class,

        // Transaction Tools
        TransactionQueryTool::class,
        SpendingAnalysisTool::class,

        // Exchange Tools
        QuoteTool::class,
        TradeTool::class,
        LiquidityPoolTool::class,

        // Compliance Tools
        AmlScreeningTool::class,
        KycTool::class,

        // Agent Protocol Tools
        AgentPaymentTool::class,
        AgentEscrowTool::class,
        AgentReputationTool::class,

        // x402 Payment Tools
        X402PaymentTool::class,

        // Visa CLI Payment Tools
        VisaCliPaymentTool::class,
        VisaCliCardsTool::class,

        // Machine Payments Protocol Tools
        MppPaymentTool::class,
        MppDiscoveryTool::class,

        // AP2 Mandate Tools
        AgentMandateTool::class,
        AgentVdcTool::class,

        // SMS Tools
        SmsSendTool::class,

        // Ramp Tools (on/off-ramp via Stripe Bridge)
        RampStartTool::class,
        RampStatusTool::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Register ToolRegistry as a singleton
        $this->app->singleton(ToolRegistry::class, function () {
            return new ToolRegistry();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Skip tool registration during tests if not explicitly needed
        if ($this->app->runningUnitTests() && ! config('ai.register_mcp_tools_in_tests', false)) {
            return;
        }

        $registry = $this->app->make(ToolRegistry::class);
        $rampUnavailable = $this->isRampDisabledInProduction();

        foreach ($this->tools as $toolClass) {
            // Ramp tools depend on a real provider; in prod the mock provider
            // throws on construction. Skip registration silently rather than
            // logging "Failed to register" warnings on every request boot.
            $isRampTool = $toolClass === RampStartTool::class || $toolClass === RampStatusTool::class;
            if ($rampUnavailable && $isRampTool) {
                continue;
            }

            try {
                $tool = $this->app->make($toolClass);
                $registry->register($tool);
            } catch (Exception $e) {
                // Log error but continue registering other tools
                Log::warning("Failed to register MCP tool: {$toolClass}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * True when Ramp tools cannot be instantiated under the current config.
     * The mock provider is the default and refuses to load in production, so
     * we treat that as "no real provider configured" and skip the tools.
     */
    private function isRampDisabledInProduction(): bool
    {
        return $this->app->environment('production')
            && config('ramp.default_provider') === 'mock';
    }
}
