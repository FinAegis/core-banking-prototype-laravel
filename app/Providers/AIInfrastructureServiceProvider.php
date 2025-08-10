<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\AI\LLM\ClaudeProvider;
use App\Infrastructure\AI\LLM\LLMProviderInterface;
use App\Infrastructure\AI\LLM\OpenAIProvider;
use App\Infrastructure\AI\Storage\ConversationStore;
use App\Infrastructure\AI\Storage\ConversationStoreInterface;
use App\Infrastructure\AI\VectorDB\PineconeProvider;
use App\Infrastructure\AI\VectorDB\VectorDatabaseInterface;
use Illuminate\Support\ServiceProvider;

class AIInfrastructureServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register LLM Provider
        $this->app->bind(LLMProviderInterface::class, function ($app) {
            $provider = config('services.ai.llm_provider', 'openai');

            return match ($provider) {
                'claude' => new ClaudeProvider(),
                'openai' => new OpenAIProvider(),
                default  => new OpenAIProvider(),
            };
        });

        // Register named LLM providers
        $this->app->bind('llm.openai', OpenAIProvider::class);
        $this->app->bind('llm.claude', ClaudeProvider::class);

        // Register Conversation Store
        $this->app->singleton(ConversationStoreInterface::class, ConversationStore::class);

        // Register Vector Database
        $this->app->singleton(VectorDatabaseInterface::class, function ($app) {
            $provider = config('services.ai.vector_db_provider', 'pinecone');

            return match ($provider) {
                'pinecone' => new PineconeProvider(),
                default    => new PineconeProvider(),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Initialize vector database index if needed
        if (config('services.ai.auto_create_index', false)) {
            try {
                $vectorDb = $this->app->make(VectorDatabaseInterface::class);
                $vectorDb->createIndex(
                    config('services.pinecone.index_name', 'finaegis-ai'),
                    1536, // OpenAI embedding dimensions
                    'cosine'
                );
            } catch (\Exception $e) {
                \Log::warning('Could not create vector database index', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
