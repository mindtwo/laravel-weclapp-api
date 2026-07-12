<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi;

use Illuminate\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WeclappApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-weclapp-api')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(WeclappClient::class, function (Application $app): WeclappClient {
            return new WeclappClient(
                (string) config('weclapp-api.base_url'),
                (string) config('weclapp-api.token'),
            );
        });
    }
}
