<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

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
        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);

        // Correo con Microsoft Graph, desde el buzón compartido sgp@dasavena.com (MAIL_MAILER=microsoft-graph)
        Mail::extend('microsoft-graph', fn () => new MicrosoftGraphTransport(
            (string) config('services.microsoft_graph.tenant_id'),
            (string) config('services.microsoft_graph.client_id'),
            (string) config('services.microsoft_graph.client_secret'),
            (string) config('services.microsoft_graph.remitente'),
        ));
    }
}