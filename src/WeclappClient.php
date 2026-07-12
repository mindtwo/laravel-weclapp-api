<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
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

class WeclappClient
{
    /**
     * A freshly configured HTTP client (base URL, auth header, timeouts, retry).
     *
     * Built per call from current config rather than cached so it always binds
     * to the live HTTP factory and configuration — important for Http::fake()
     * and config overrides in tests, and for reuse by the lazy proxy and job.
     */
    public function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('weclapp-api.base_url'), '/').'/';

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'AuthenticationToken' => (string) config('weclapp-api.token'),
                'Accept'              => 'application/json',
                'Content-Type'        => 'application/json',
            ])
            ->timeout((int) config('weclapp-api.http.timeout', 60))
            ->connectTimeout((int) config('weclapp-api.http.connect_timeout', 10))
            ->retry(
                (int) config('weclapp-api.http.retry_times', 3),
                (int) config('weclapp-api.http.retry_sleep', 500),
                throw: false,
            );
    }

    /**
     * Fetch every record from a paginated collection endpoint, merging all pages.
     *
     * @param array<string, mixed> $params
     *
     * @throws RequestException
     *
     * @return Collection<int, object>
     */
    public function get(string $endpoint, array $params = []): Collection
    {
        $pageSize = (int) config('weclapp-api.page_size', 1000);
        $results = [];
        $page = 1;

        do {
            $response = $this->client()->get($this->path($endpoint), array_merge($params, [
                'page'     => $page,
                'pageSize' => $pageSize,
            ]));

            $response->throw();

            $batch = array_values(array_filter(
                (array) ($response->object()->result ?? []),
                'is_object',
            ));

            $results = [...$results, ...$batch];

            $page++;
        } while (count($batch) >= $pageSize);

        return collect($results);
    }

    /**
     * Fetch a single record by id. Returns null on a 404.
     *
     * @throws RequestException
     */
    public function find(string $endpoint, string|int $id): ?object
    {
        $response = $this->client()->get($this->path($endpoint).'/'.$id);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->object();
    }

    /**
     * The number of records matching the given filters.
     *
     * @param array<string, mixed> $params
     *
     * @throws RequestException
     */
    public function count(string $endpoint, array $params = []): int
    {
        $response = $this->client()->get($this->path($endpoint).'/count', $params);

        $response->throw();

        return (int) ($response->object()->result ?? 0);
    }

    /**
     * Immediately POST data to an endpoint. When $dryRun is true Weclapp
     * validates the payload without persisting it (server-side dry run).
     *
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     *
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data, bool $dryRun = false): array
    {
        if ($this->writesSuppressed()) {
            $this->recordSuppressedWrite('POST', $endpoint);

            return [];
        }

        $path = $this->path($endpoint);

        if ($dryRun) {
            $path .= '?dryRun=true';
        }

        $response = $this->client()->post($path, $data);

        $response->throw();

        return $response->json();
    }

    /**
     * Whether mutating requests should be suppressed (logged, not sent).
     *
     * Defaults to allowing writes, so a missing/misconfigured flag never
     * silently blocks production traffic — only an explicit false suppresses.
     */
    public function writesSuppressed(): bool
    {
        return config('weclapp-api.writes_enabled', true) === false;
    }

    /**
     * Log a suppressed write and emit a completion event marked suppressed, so
     * consumers observe the intent without any outbound traffic.
     */
    public function recordSuppressedWrite(string $method, string $endpoint): void
    {
        $channel = config('weclapp-api.logging.channel');

        Log::channel(is_string($channel) && $channel !== '' ? $channel : null)->info(
            'Weclapp write suppressed (writes disabled)',
            ['method' => $method, 'endpoint' => $endpoint],
        );

        WeclappApiCallCompleted::dispatch($endpoint, $method, [], 200, true, true);
    }

    public function parties(): Party
    {
        return app(Party::class);
    }

    public function customers(): Customer
    {
        return app(Customer::class);
    }

    public function suppliers(): Supplier
    {
        return app(Supplier::class);
    }

    public function projects(): Project
    {
        return app(Project::class);
    }

    public function articles(): Article
    {
        return app(Article::class);
    }

    public function articleCategories(): ArticleCategory
    {
        return app(ArticleCategory::class);
    }

    public function quotations(): Quotation
    {
        return app(Quotation::class);
    }

    public function salesOrders(): SalesOrder
    {
        return app(SalesOrder::class);
    }

    public function users(): User
    {
        return app(User::class);
    }

    public function salesInvoices(): SalesInvoice
    {
        return app(SalesInvoice::class);
    }

    public function purchaseOrders(): PurchaseOrder
    {
        return app(PurchaseOrder::class);
    }

    public function purchaseInvoices(): PurchaseInvoice
    {
        return app(PurchaseInvoice::class);
    }

    public function blanketSalesOrders(): BlanketSalesOrder
    {
        return app(BlanketSalesOrder::class);
    }

    public function contracts(): Contract
    {
        return app(Contract::class);
    }

    public function opportunities(): Opportunity
    {
        return app(Opportunity::class);
    }

    public function units(): Unit
    {
        return app(Unit::class);
    }

    public function taxes(): Tax
    {
        return app(Tax::class);
    }

    public function paymentMethods(): PaymentMethod
    {
        return app(PaymentMethod::class);
    }

    public function termsOfPayment(): TermOfPayment
    {
        return app(TermOfPayment::class);
    }

    public function customerCategories(): CustomerCategory
    {
        return app(CustomerCategory::class);
    }

    public function leadSources(): LeadSource
    {
        return app(LeadSource::class);
    }

    public function salesStages(): SalesStage
    {
        return app(SalesStage::class);
    }

    public function currencies(): Currency
    {
        return app(Currency::class);
    }

    public function costCenters(): CostCenter
    {
        return app(CostCenter::class);
    }

    public function ledgerAccounts(): LedgerAccount
    {
        return app(LedgerAccount::class);
    }

    public function warehouses(): Warehouse
    {
        return app(Warehouse::class);
    }

    public function shipments(): Shipment
    {
        return app(Shipment::class);
    }

    public function documents(): Document
    {
        return app(Document::class);
    }

    public function comments(): Comment
    {
        return app(Comment::class);
    }

    public function webhooks(): Webhook
    {
        return app(Webhook::class);
    }

    protected function path(string $endpoint): string
    {
        return ltrim($endpoint, '/');
    }
}
