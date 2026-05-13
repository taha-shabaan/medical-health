<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Services\Rag\EmbeddingService;
use App\Services\Rag\GeminiLlmService;
use App\Services\Rag\PineconeService;
use App\Services\Rag\PromptBuilderService;
use App\Policies\ConversationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmbeddingService::class);   // Gemini embeddings
        $this->app->singleton(GeminiLlmService::class);   // Gemini chat with fallbacks
        $this->app->singleton(PineconeService::class);    // Pinecone (dim=768)
        $this->app->singleton(PromptBuilderService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);

        $this->logMissingRagConfiguration();
    }

    private function logMissingRagConfiguration(): void
    {
        $missing = [];

        if (!config('services.gemini.key')) {
            $missing[] = 'services.gemini.key';
        }

        if (config('rag.enabled', false)) {
            if (!config('services.pinecone.key')) {
                $missing[] = 'services.pinecone.key';
            }

            if (!config('services.pinecone.host')) {
                $missing[] = 'services.pinecone.host';
            }
        }

        if ($missing !== []) {
            Log::warning('RAG services are not fully configured.', [
                'missing' => $missing,
            ]);
        }
    }
}
