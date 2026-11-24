<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\AI\Agents\ComplianceAgent;
use App\Domain\AI\Agents\FinancialAgent;
use App\Domain\AI\Agents\GeneralAgent;
use App\Domain\AI\Agents\TradingAgent;
use App\Domain\AI\Agents\TransferAgent;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\Services\AgentOrchestratorService;
use App\Domain\AI\Services\AgentResponseComposerService;
use App\Domain\AI\Services\AgentRouterService;
use App\Domain\AI\Services\LLMOrchestrationService;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One shared instance, so a provider registered with registerProvider()
        // is seen by the agents and by AgentOrchestratorService alike.
        $this->app->singleton(LLMOrchestrationService::class);

        $this->app->singleton(AgentResponseComposerService::class);

        $this->app->singleton(AgentRouterService::class, function ($app) {
            $router = new AgentRouterService();
            $toolRegistry = $app->make(ToolRegistry::class);
            $llmService = $app->make(LLMOrchestrationService::class);

            $router->registerAgent(new FinancialAgent($toolRegistry, $llmService));
            $router->registerAgent(new TradingAgent($toolRegistry, $llmService));
            $router->registerAgent(new ComplianceAgent($toolRegistry, $llmService));
            $router->registerAgent(new TransferAgent($toolRegistry, $llmService));
            $router->registerAgent(new GeneralAgent($toolRegistry, $llmService));

            return $router;
        });

        $this->app->singleton(AgentOrchestratorService::class);
    }
}
