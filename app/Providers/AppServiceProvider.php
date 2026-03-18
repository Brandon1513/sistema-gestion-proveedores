<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

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
        ResetPassword::createUrlUsing(function ($user, string $token) {
    $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5175'));
    return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
    });
    }
}
