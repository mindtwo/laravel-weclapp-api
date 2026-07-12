<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Facades;

use Illuminate\Support\Facades\Facade;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Article;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\BlanketSalesOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Comment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Contract;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CostCenter;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Currency;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Customer;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CustomerCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Document;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LeadSource;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LedgerAccount;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Opportunity;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Party;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PaymentMethod;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Project;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseInvoice;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Quotation;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesStage;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Shipment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Supplier;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Tax;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TermOfPayment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Unit;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\User;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Warehouse;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Webhook;
use Mindtwo\LaravelWeclappApi\WeclappClient as BaseWeclappClient;

/**
 * @method static \Illuminate\Support\Collection<int, object> get(string $endpoint, array<string, mixed> $params = [])
 * @method static object|null find(string $endpoint, string|int $id)
 * @method static int count(string $endpoint, array<string, mixed> $params = [])
 * @method static array<string, mixed> post(string $endpoint, array<string, mixed> $data, bool $dryRun = false)
 * @method static Party parties()
 * @method static Customer customers()
 * @method static Supplier suppliers()
 * @method static Project projects()
 * @method static Article articles()
 * @method static ArticleCategory articleCategories()
 * @method static Quotation quotations()
 * @method static SalesOrder salesOrders()
 * @method static User users()
 * @method static SalesInvoice salesInvoices()
 * @method static PurchaseOrder purchaseOrders()
 * @method static PurchaseInvoice purchaseInvoices()
 * @method static BlanketSalesOrder blanketSalesOrders()
 * @method static Contract contracts()
 * @method static Opportunity opportunities()
 * @method static Unit units()
 * @method static Tax taxes()
 * @method static PaymentMethod paymentMethods()
 * @method static TermOfPayment termsOfPayment()
 * @method static CustomerCategory customerCategories()
 * @method static LeadSource leadSources()
 * @method static SalesStage salesStages()
 * @method static Currency currencies()
 * @method static CostCenter costCenters()
 * @method static LedgerAccount ledgerAccounts()
 * @method static Warehouse warehouses()
 * @method static Shipment shipments()
 * @method static Document documents()
 * @method static Comment comments()
 * @method static Webhook webhooks()
 *
 * @see BaseWeclappClient
 */
class WeclappClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseWeclappClient::class;
    }
}
