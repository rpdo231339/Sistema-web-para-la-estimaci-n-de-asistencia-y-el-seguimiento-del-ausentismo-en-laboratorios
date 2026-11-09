<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\PaseLista;
use App\Models\Sesion;
use App\Observers\PaseListaObserver;
use App\Observers\SesionObserver;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
       Paginator::useBootstrapFive();
       PaseLista::observe(PaseListaObserver::class);
       Sesion::observe(SesionObserver::class);
    }
}
