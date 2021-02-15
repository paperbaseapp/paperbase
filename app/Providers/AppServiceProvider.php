<?php

namespace App\Providers;

use App\Models\AuthenticatedUser;
use App\Models\User;
use App\Search\MeilisearchEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use MeiliSearch\Client;
use Symfony\Component\Filesystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(User::class, fn(): ?User => User::current());
        $this->app->singleton(Filesystem::class, fn(): Filesystem => new Filesystem());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Temporary solution to include filtered metadata as scoutMetadata in search results
        resolve(EngineManager::class)->extend('meilisearch', function () {
            return new MeilisearchEngine(
                resolve(Client::class),
                config('scout.soft_delete', false)
            );
        });
    }
}
