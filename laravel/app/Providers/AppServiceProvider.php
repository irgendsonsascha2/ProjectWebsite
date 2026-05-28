<?php

namespace App\Providers;

use App\Services\SiteDisplayName;
use App\Support\MailDisplayName;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(function () {
            return Password::min(12)->max(512);
        });

        $displayName = MailDisplayName::sanitize(app(SiteDisplayName::class)->resolve());
        config(['app.name' => $displayName]);
        config(['mail.from.name' => $displayName]);

        Event::listen(MessageSending::class, [MailDisplayName::class, 'listen']);
    }
}
