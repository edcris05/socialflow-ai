<?php

namespace App\Providers;

use App\Services\Knowledge\KnowledgeRetrieverInterface;
use App\Services\Knowledge\TextKnowledgeRetriever;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KnowledgeRetrieverInterface::class, TextKnowledgeRetriever::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
