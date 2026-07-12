<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Article;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\BlanketSalesOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Comment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Contract;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CostCenter;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Currency;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CustomerCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Document;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Endpoint;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LeadSource;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LedgerAccount;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Opportunity;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Party;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PaymentMethod;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseInvoice;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Quotation;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesStage;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Shipment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Tax;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TermOfPayment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Unit;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\User;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Warehouse;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Webhook;
use Mindtwo\LaravelWeclappApi\Listeners\LogWeclappEvent;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WeclappApiServiceProvider extends PackageServiceProvider
{
    /**
     * Every typed endpoint class, registered as a singleton and reachable via
     * the matching WeclappClient accessor.
     *
     * @var list<class-string<Endpoint>>
     */
    private const array ENDPOINTS = [
        Party::class,
        Article::class,
        ArticleCategory::class,
        Quotation::class,
        SalesOrder::class,
        User::class,
        SalesInvoice::class,
        PurchaseOrder::class,
        PurchaseInvoice::class,
        BlanketSalesOrder::class,
        Contract::class,
        Opportunity::class,
        Unit::class,
        Tax::class,
        PaymentMethod::class,
        TermOfPayment::class,
        CustomerCategory::class,
        LeadSource::class,
        SalesStage::class,
        Currency::class,
        CostCenter::class,
        LedgerAccount::class,
        Warehouse::class,
        Shipment::class,
        Document::class,
        Comment::class,
        Webhook::class,
    ];

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

        foreach (self::ENDPOINTS as $endpoint) {
            $this->app->singleton($endpoint);
        }
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        RateLimiter::for('weclapp-api-jobs', function (): Limit {
            return Limit::perMinute((int) config('weclapp-api.rate_limit_per_minute', 100))->by('weclapp-api-jobs');
        });

        if (config('weclapp-api.logging.enabled', false)) {
            Event::listen(WeclappApiCallCompleted::class, LogWeclappEvent::class);
        }
    }
}
