<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // \DB::listen(function ($query) {
        //     \Log::info("SQL Query Executed: {$query->sql} with bindings: ".implode(', ', $query->bindings));
        // });
    }
}
