<?php

namespace App\Providers;

use App\Contracts\GenerationProviderInterface;
use App\Services\Generation\OpenAIGenerationProvider;
use App\Services\Knowledge\KnowledgeRetrieverInterface;
use App\Services\Knowledge\TextKnowledgeRetriever;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KnowledgeRetrieverInterface::class, TextKnowledgeRetriever::class);
        $this->app->bind(GenerationProviderInterface::class, OpenAIGenerationProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
