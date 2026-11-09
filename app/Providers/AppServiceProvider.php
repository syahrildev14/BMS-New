<?php

namespace App\Providers;

use App\Contracts\PdfConverter;
use App\Services\LibreOfficePdfConverter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfConverter::class, LibreOfficePdfConverter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
