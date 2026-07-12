<?php

namespace Mindtwo\LaravelWeclappApi\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\WeclappApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Mindtwo\\LaravelWeclappApi\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            WeclappApiServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // Migrations are publish-only for consumers, so load them explicitly
        // for the package's own test database.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
        config()->set('weclapp-api.token', 'test-token');
        config()->set('weclapp-api.timezone', 'UTC');
        // Writes are on by default in the package's own tests; the suppression
        // test overrides this to false. (In a real app the config defaults to
        // off on local/testing.)
        config()->set('weclapp-api.writes_enabled', true);

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }
}
