<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\MCP\Tools\Account\AccountBalanceTool;
use App\Domain\AI\MCP\Tools\Account\CreateAccountTool;
use App\Domain\AI\MCP\Tools\Account\DepositTool;
use App\Domain\AI\MCP\Tools\Account\WithdrawTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentEscrowTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentPaymentTool;
use App\Domain\AI\MCP\Tools\AgentProtocol\AgentReputationTool;
use App\Domain\AI\MCP\Tools\Compliance\AmlScreeningTool;
use App\Domain\AI\MCP\Tools\Compliance\KycTool;
use App\Domain\AI\MCP\Tools\Exchange\LiquidityPoolTool;
use App\Domain\AI\MCP\Tools\Exchange\QuoteTool;
use App\Domain\AI\MCP\Tools\Exchange\TradeTool;
use App\Domain\AI\MCP\Tools\Payment\PaymentStatusTool;
use App\Domain\AI\MCP\Tools\Payment\TransferTool;
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

        foreach ($this->tools as $toolClass) {
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
}
