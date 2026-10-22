<?php

namespace App\Providers;

use App\Pdf\EmbeddedFonts;
use App\Pdf\TimedGotenbergDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('laravel-pdf.driver.gotenberg', fn () => new TimedGotenbergDriver(config('laravel-pdf.gotenberg', [])));

        $this->app->singleton(EmbeddedFonts::class, fn () => new EmbeddedFonts(base_path('node_modules')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('pdf', fn (Request $request) => Limit::perMinute(10)
            ->by((string) $request->ip())
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => "You've created a lot of PDFs in the last minute. Please wait a moment and try again.",
            ], 429, $headers)));
    }
}
