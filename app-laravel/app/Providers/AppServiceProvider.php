<?php

namespace App\Providers;

use App\Contracts\GenerationEvaluatorInterface;
use App\Contracts\GenerationProviderInterface;
use App\Contracts\MetaPublisherInterface;
use App\Contracts\PublicHostResolverInterface;
use App\Contracts\PublicMediaStorageInterface;
use App\Contracts\StrategyProviderInterface;
use App\Services\Generation\DeterministicGenerationEvaluator;
use App\Services\Generation\OpenAIGenerationProvider;
use App\Services\Knowledge\KnowledgeRetrieverInterface;
use App\Services\Knowledge\TextKnowledgeRetriever;
use App\Services\Meta\InstagramMetaPublisher;
use App\Services\Publishing\DnsPublicHostResolver;
use App\Services\Publishing\LaravelFilesystemPublicMediaStorage;
use App\Services\Strategy\OpenAIStrategyProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KnowledgeRetrieverInterface::class, TextKnowledgeRetriever::class);
        $this->app->bind(GenerationProviderInterface::class, OpenAIGenerationProvider::class);
        $this->app->bind(GenerationEvaluatorInterface::class, DeterministicGenerationEvaluator::class);
        $this->app->bind(StrategyProviderInterface::class, OpenAIStrategyProvider::class);
        $this->app->bind(MetaPublisherInterface::class, InstagramMetaPublisher::class);
        $this->app->bind(PublicHostResolverInterface::class, DnsPublicHostResolver::class);
        $this->app->bind(PublicMediaStorageInterface::class, LaravelFilesystemPublicMediaStorage::class);
    }

    public function boot(): void
    {
        //
    }
}
