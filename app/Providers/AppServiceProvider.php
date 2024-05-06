<?php

namespace App\Providers;

use App\Repositories\DynamicsRepository;
use App\Repositories\DynamicsRepositoryInterface;
use App\Repositories\SharePointRepository;
use App\Repositories\SharePointRepositoryInterface;
use App\Repositories\TakRepository;
use App\Repositories\TakRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TakRepositoryInterface::class, TakRepository::class);
        $this->app->bind(DynamicsRepositoryInterface::class, DynamicsRepository::class);
        $this->app->bind(SharePointRepositoryInterface::class, SharePointRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
