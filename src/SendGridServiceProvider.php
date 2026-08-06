<?php

namespace NotificationChannels\SendGrid;

use SendGrid;
use Illuminate\Support\ServiceProvider;

class SendGridServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/sendgrid.php' => config_path('sendgrid.php'),
        ], 'sendgrid-config');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/sendgrid.php',
            'sendgrid'
        );

        $this->app->when(SendGridChannel::class)
            ->needs(SendGrid::class)
            ->give(function () {
                return new SendGrid(
                    $this->app['config']['sendgrid.api_key']
                        ?? $this->app['config']['services.sendgrid.api_key']
                );
            });

        $this->app->when(SendGridChannel::class)
            ->needs('$testRecipient')
            ->give(function () {
                return $this->app['config']['sendgrid.test_recipient'];
            });

        $this->app->when(SendGridChannel::class)
            ->needs('$liveMode')
            ->give(function () {
                return (bool) $this->app['config']['sendgrid.live_mode'];
            });
    }
}
