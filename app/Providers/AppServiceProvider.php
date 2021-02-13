<?php

namespace App\Providers;

use App\Models\AuthenticatedUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
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
        //
    }
}
