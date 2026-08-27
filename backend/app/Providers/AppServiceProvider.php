<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Muajt, ditët dhe "para 5 minutash" të dalin shqip te njoftimet
        // dhe te titulli i raportit mujor.
        Carbon::setLocale(config('app.locale'));
    }
}
