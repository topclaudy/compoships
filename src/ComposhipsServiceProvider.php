<?php
// src/ComposhipsServiceProvider.php

namespace Topclaudy\Compoships;

use Illuminate\Support\ServiceProvider;

class ComposhipsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge the package config so defaults are available
        $this->mergeConfigFrom(__DIR__ . '/../config/compoships.php', 'compoships');
    }

    public function boot()
    {
        // Allow publishing to the app's config folder
        $this->publishes([
            __DIR__ . '/../config/compoships.php' => config_path('compoships.php'),
        ], 'compoships-config');
    }
}