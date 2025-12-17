<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

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
        // Register event listeners
        Event::listen(
            \App\Events\PenilaianSLHDUpdated::class,
            \App\Listeners\HandleUnfinalizedPenilaianSLHD::class
        );

        Event::listen(
            \App\Events\PenilaianPenghargaanUpdated::class,
            \App\Listeners\HandleUnfinalizedPenilaianPenghargaan::class
        );

        Event::listen(
            \App\Events\Validasi1Updated::class,
            \App\Listeners\HandleUnfinalizedValidasi1::class
        );

        Event::listen(
            \App\Events\Validasi2Updated::class,
            \App\Listeners\HandleUnfinalizedValidasi2::class
        );
    }
}
