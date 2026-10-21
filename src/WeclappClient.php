<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi;

use Closure;
use Generator;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\AccountingTransaction;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Approval;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ApprovalGroup;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ApprovalRule;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArchivedEmail;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Article;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleAccountingCode;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleCategoryClassification;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleItemGroup;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticlePrice;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleRating;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleStatus;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ArticleSupplySource;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Attendance;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\BankAccount;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\BankTransaction;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\BatchNumber;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\BlanketPurchaseOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\BlanketSalesOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Calendar;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CalendarEvent;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Campaign;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CampaignParticipant;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CashAccount;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CashAccountSheet;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CashAccountTransaction;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Comment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CommercialLanguage;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CompanySize;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Contract;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ContractAuthorizationUnit;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ContractBillingGroup;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ContractTerminationReason;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ContractType;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CostCenter;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CostCenterGroup;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CostType;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CrmCallCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CrmEvent;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CrmEventCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Currency;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CustomAttributeDefinition;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Customer;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CustomerCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CustomerLeadLossReason;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CustomerTopic;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\CustomsTariffNumber;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Document;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ExternalConnection;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\FinancialYear;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\FulfillmentProvider;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\IncomingGoods;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\InternalTransportReference;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Inventory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\InventoryGroup;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\InventoryItem;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\InventoryTransportReference;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LeadRating;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LeadSource;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LedgerAccount;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LegalForm;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\LoadingEquipmentIdentifier;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\MailTemplate;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Manufacturer;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Notification;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\NumberRange;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\NumberRangeValue;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Opportunity;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\OpportunityTopic;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\OpportunityWinLossReason;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Party;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PartyRating;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PaymentMethod;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PaymentRun;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PaymentRunItem;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PerformanceRecord;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PersonalAccountingCode;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PersonDepartment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PersonRole;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Pick;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PickCheckReason;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PlaceOfService;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PriceCalculationParameter;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ProductionOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ProductionWorkSchedule;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ProductionWorkScheduleAssignment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Project;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ProjectOrderStatusPage;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseInvoice;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseOpenItem;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseOrderRequest;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\PurchaseRequisition;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Quotation;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Rebate;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\RecordEmailingRule;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Region;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Reminder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\RemotePrintJob;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesOpenItem;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesStage;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SalesTeam;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Sector;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SepaDirectDebitMandate;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\SerialNumber;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ServiceQuota;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Shelf;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Shipment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ShipmentMethod;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ShipmentReturnAssessment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ShipmentReturnError;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ShipmentReturnReason;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ShipmentReturnRectification;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\ShippingCarrier;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\StorageLocation;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\StoragePlace;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\StoragePlaceBlockingReason;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\StoragePlaceSize;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Supplier;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Tag;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Task;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TaskList;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TaskTemplate;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Tax;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TaxDeterminationRule;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TermOfPayment;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Ticket;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketAssignmentRule;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketCategory;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketChannel;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketFaq;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketPoolingGroup;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketPriority;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketServiceLevelAgreement;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketStatus;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TicketType;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TimeRecord;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Title;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Translation;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\TransportationOrder;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Unit;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\User;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\UserRole;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\VariantArticle;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\VariantArticleAttribute;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\VariantArticleVariant;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Warehouse;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\WarehouseStock;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\WarehouseStockMovement;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Webhook;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\WeclappOs;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\WorkScheduleProfile;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use UnexpectedValueException;

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
        $baseUrl = rtrim((string) config('weclapp-api.base_url'), '/');
        $token = (string) config('weclapp-api.token');

        // A missing setting once sent every request unauthenticated; fail here
        // instead, naming what to set.
        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException(sprintf(
                'Weclapp is not configured: set %s.',
                $baseUrl === '' ? 'MINDTWO_WECLAPP_URL' : 'MINDTWO_WECLAPP_API_KEY',
            ));
        }

        return Http::baseUrl($baseUrl.'/')
            ->withHeaders([
                'AuthenticationToken' => $token,
                'Accept'              => 'application/json',
                'Content-Type'        => 'application/json',
                // The spec asks every automated client to identify itself.
                'User-Agent' => 'mindtwo/laravel-weclapp-api',
            ])
            ->timeout((int) config('weclapp-api.http.timeout', 60))
            ->connectTimeout((int) config('weclapp-api.http.connect_timeout', 10))
            ->retry(
                (int) config('weclapp-api.http.retry_times', 3),
                fn (int $attempt, Throwable $e): int => $this->retryDelay($attempt, $e),
                fn (Throwable $e, PendingRequest $request, ?string $method): bool => $this->shouldRetry($e, $method),
                throw: false,
            );
    }

    /**
     * Whether a failed attempt may be sent again.
     *
     * A connection error or a 5xx leaves it open whether Weclapp processed the
     * request, so only idempotent verbs are retried on those — re-sending a
     * POST could create a second quotation or order. A 429 means Weclapp
     * rejected the request before doing anything, so any verb may retry. Every
     * other 4xx is a refusal that a retry cannot change, and find()/download()
     * rely on a single 404 to mean "no such record".
     */
    protected function shouldRetry(Throwable $e, ?string $method): bool
    {
        $idempotent = in_array($method, ['GET', 'HEAD', 'PUT', 'DELETE'], true);

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status === 429 || ($status >= 500 && $idempotent);
        }

        return $idempotent;
    }

    /**
     * Milliseconds to wait before the next attempt: a numeric Retry-After from
     * Weclapp wins (capped, since it can queue requests for up to 30 s), else
     * the configured sleep grows linearly with the attempt.
     */
    protected function retryDelay(int $attempt, Throwable $e): int
    {
        $retryAfter = $e instanceof RequestException ? $e->response->header('Retry-After') : null;

        if (is_numeric($retryAfter)) {
            return min(30, max(1, (int) $retryAfter)) * 1000;
        }

        return $attempt * (int) config('weclapp-api.http.retry_sleep', 500);
    }

    /**
     * Fetch every record from a paginated collection endpoint, merging all pages.
     *
     * The whole collection is held in memory; lazy() walks the same pages one at
     * a time. A caller that passes `page` gets exactly that page (with its own
     * `pageSize`, and `offset` if given), sorted by id unless it sorts itself.
     * Those are offset pages: a record deleted between two calls shifts the ones
     * behind it, so walk a collection too large to hold with lazy() instead.
     *
     * @param array<string, mixed> $params
     *
     * @throws RequestException
     * @throws InvalidArgumentException for an `offset` without `page`
     * @throws UnexpectedValueException when Weclapp ignores the cursor or `page`, or returns records without an id
     *
     * @return Collection<int, object>
     */
    public function get(string $endpoint, array $params = []): Collection
    {
        // A caller asking for a page gets that page, not the whole collection —
        // sorted by id unless it orders the result itself, so page 3 means the
        // same records on every call.
        if (isset($params['page'])) {
            $response = $this->client()->get($this->path($endpoint), [
                ...(isset($params['sort']) || isset($params['orderBy']) ? [] : ['sort' => 'id']),
                ...$params,
                'pageSize' => max(1, min(1000, (int) ($params['pageSize'] ?? config('weclapp-api.page_size', 1000)))),
            ]);

            $response->throw();

            return collect(array_values(array_filter((array) ($response->object()->result ?? []), 'is_object')));
        }

        return collect($this->lazy($endpoint, $params)->all());
    }

    /**
     * Every record of a paginated collection endpoint, fetched a page at a time
     * as the caller consumes them, so memory holds one page rather than the
     * whole collection.
     *
     * Pages are walked by key, not by offset: sorted by id, each request asks
     * for the ids above the last one seen. Weclapp documents no default order,
     * so offset pages over a collection that changes mid-read shift under the
     * client — a record deleted on an earlier page moves the first record of
     * the next page out of reach, and the sync then archives it as gone. A key
     * cursor cannot shift. The loop ends on an empty page rather than a short
     * one, because a resource whose cap is lower than the configured page size
     * answers every page short.
     *
     * A caller that orders the result itself (`sort` or `orderBy`), or filters on
     * the id, gets plain offset pages: a second sort or id filter would contradict
     * its own. `properties` always gains `id`, which paging needs.
     *
     * A failed page throws when the walk reaches it, after the records before it
     * have been handed out; a caller acting on the whole set (as reconciliation
     * does) must only do so once the iteration has finished.
     *
     * @param array<string, mixed> $params
     *
     * @throws InvalidArgumentException for `page` or `offset`, which address a single page
     *
     * @return LazyCollection<int, object> iterating it throws RequestException on a failed
     *                                     page, and UnexpectedValueException when Weclapp ignores
     *                                     the cursor or `page`, or returns records without an id
     */
    public function lazy(string $endpoint, array $params = []): LazyCollection
    {
        // A walk covers the whole collection: `page` would be overwritten by the
        // cursor, and an offset sent with every page would skip records on each.
        if (isset($params['page']) || isset($params['offset'])) {
            throw new InvalidArgumentException('`page` and `offset` address a single page; pass `page` to get() instead.');
        }

        // Both loops need the id to move on, so a partial response always carries it.
        if (isset($params['properties']) && is_string($params['properties'])
            && ! in_array('id', explode(',', $params['properties']), true)) {
            $params['properties'] .= ',id';
        }

        return LazyCollection::make(fn (): Generator => $this->walk($endpoint, $params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return Generator<int, object>
     */
    private function walk(string $endpoint, array $params): Generator
    {
        // Weclapp caps pageSize at 1000; anything below 1 would never end the loop.
        $pageSize = max(1, min(1000, (int) config('weclapp-api.page_size', 1000)));
        // Weclapp rejects `sort` together with `orderBy`, so either one opts out of the cursor.
        $keyset = ! isset($params['sort']) && ! isset($params['orderBy']) && ! $this->filtersOnId($params);
        $page = 1;
        $lastId = null;
        $previousFirstId = null;

        do {
            $query = array_merge($params, ['page' => $page, 'pageSize' => $pageSize]);

            if ($keyset) {
                $query['sort'] = 'id';
                $query['page'] = 1;

                if ($lastId !== null) {
                    $query['id-gt'] = $lastId;
                }
            }

            $response = $this->client()->get($this->path($endpoint), $query);

            $response->throw();

            $batch = array_values(array_filter(
                (array) ($response->object()->result ?? []),
                'is_object',
            ));

            $pageLastId = $batch === [] ? null : (end($batch)->id ?? null);

            // Sorted by id and filtered above the last one, a genuine page never
            // repeats an id. A repeat means the cursor was ignored: a short page
            // already held the whole collection, a full one would silently drop
            // everything behind it, which the sync would then archive.
            if ($keyset && $lastId !== null && $pageLastId !== null && (int) $pageLastId <= (int) $lastId) {
                if (count($batch) >= $pageSize) {
                    throw new UnexpectedValueException(sprintf(
                        'Weclapp ignored the id cursor on /%s: page after id %s ends at id %s.',
                        $endpoint,
                        $lastId,
                        $pageLastId,
                    ));
                }

                break;
            }

            // Offset pages end on an empty page too, so a page that repeats the
            // previous one means `page` was ignored and the loop would not end.
            $firstId = $batch === [] ? null : ($batch[0]->id ?? null);

            if (! $keyset && $firstId !== null && $firstId === $previousFirstId) {
                throw new UnexpectedValueException(sprintf('Weclapp ignored the page parameter on /%s.', $endpoint));
            }

            // A record without an id cannot move the cursor; handing on what was
            // read so far would look like the whole collection.
            if ($keyset && $batch !== [] && $pageLastId === null) {
                throw new UnexpectedValueException(sprintf('Weclapp returned records without an id on /%s.', $endpoint));
            }

            // Not `yield from`: that reuses each page's own keys, which restart at 0.
            foreach ($batch as $record) {
                yield $record;
            }

            $page++;
            $lastId = $pageLastId ?? $lastId;
            $previousFirstId = $firstId;
        } while ($batch !== []);
    }

    /**
     * Whether the caller already constrains the id, which a key cursor would
     * silently override.
     *
     * @param array<string, mixed> $params
     */
    private function filtersOnId(array $params): bool
    {
        foreach (array_keys($params) as $key) {
            if ($key === 'id' || str_starts_with((string) $key, 'id-')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch a single record by id. Returns null on a 404.
     *
     * @throws RequestException
     */
    public function find(string $endpoint, string|int $id): ?object
    {
        $response = $this->client()->get($this->recordPath($endpoint, $id));

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->object();
    }

    /**
     * Fetch a binary document from a resource sub-path, e.g. an article's main
     * image at `article/id/{id}/downloadMainArticleImage`.
     *
     * Returns null when Weclapp has no such document (404), so a caller can fall
     * back to its own copy without catching. Any other failure still throws —
     * a 403 or a timeout is not the same answer as "there is no image".
     *
     * @param array<string, mixed> $params
     *
     * @throws RequestException
     */
    public function download(string $path, array $params = []): ?Response
    {
        $response = $this->client()->get($this->path($path), $params);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response;
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
     * The one path every write takes, so suppression and the completion event
     * apply alike to the low-level methods, the proxy and the queued job.
     * A failed status is returned, not thrown; a connection error propagates.
     *
     * @internal Not part of the consumer API; use post(), put(), delete() or
     *           upload(), or the typed endpoints.
     *
     * @param array<string, mixed> $body
     * @param (Closure(PendingRequest): PendingRequest)|null $configure e.g. a raw upload body
     * @param bool $retry false lets the queued job retry through the queue only
     * @param (Closure(Response): bool)|null $isFinal whether a response ends the call; the
     *                                                queued job answers false for an attempt it will retry
     *
     * @throws ConnectionException
     */
    public function send(
        string $method,
        string $path,
        array $body = [],
        ?Closure $configure = null,
        bool $retry = true,
        ?Closure $isFinal = null,
    ): Response {
        if ($this->isMutating($method) && $this->writesSuppressed()) {
            $this->recordSuppressedWrite($method, $path);

            return new Response(new PsrResponse(200, [], (string) json_encode([])));
        }

        $client = $this->client();

        if (! $retry) {
            $client = $client->retry(1, 0, throw: false);
        }

        if ($configure !== null) {
            $client = $configure($client);
        }

        $response = match ($method) {
            Request::METHOD_POST   => $client->post($path, $body),
            Request::METHOD_PUT    => $client->put($path, $body),
            Request::METHOD_DELETE => $client->delete($path),
            default                => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        // A listener that throws must not turn a sent request into a failed one —
        // on the queue that would re-run the job and send the write again.
        try {
            WeclappApiCallCompleted::dispatch(
                $path,
                $method,
                $response->json() ?? [],
                $response->status(),
                $response->successful(),
                false,
                $isFinal === null || $isFinal($response),
            );
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    public function isMutating(string $method): bool
    {
        return in_array($method, [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_DELETE], true);
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
        $response = $this->send(Request::METHOD_POST, $this->path($endpoint), $data, $this->writeQuery($dryRun, false));

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Immediately PUT data to a record, replacing it. When $dryRun is true
     * Weclapp validates the payload without persisting it (server-side dry run).
     *
     * A dry run is still a PUT against the live tenant, so write suppression
     * covers it too: an environment that may not write may not validate either,
     * and a caller that needs to tell the difference must check
     * writesSuppressed() rather than read anything into the empty return.
     *
     * Without $partial every property missing from $data is set to null; with
     * it only the given properties change (send `version` too for optimistic
     * locking).
     *
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     *
     * @return array<string, mixed>
     */
    public function put(string $endpoint, string|int $id, array $data, bool $dryRun = false, bool $partial = false): array
    {
        $response = $this->send(Request::METHOD_PUT, $this->recordPath($endpoint, $id), $data, $this->writeQuery($dryRun, $partial));

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Upload a binary document to a resource sub-path, e.g. an article image at
     * `article/id/{id}/uploadArticleImage`.
     *
     * Weclapp does not take multipart here. The bytes are the raw request body
     * and the filename is the required `name` query parameter, so both are sent
     * that way; $params carries the rest of the query (`mainImage`,
     * `articleImageId`). $contentType has to describe the bytes — the endpoint
     * declares `image/jpeg`, `image/png` and `application/pdf` alongside a
     * wildcard, so the default only applies when the caller does not know.
     *
     * Returns null on a 404, so a caller can tell "no such record" from a
     * refusal without catching, as with download().
     *
     * @param array<string, mixed> $params
     *
     * @throws RequestException
     *
     * @return array<string, mixed>|null
     */
    public function upload(
        string $path,
        string $contents,
        string $filename,
        string $contentType = 'application/octet-stream',
        array $params = [],
    ): ?array {
        $response = $this->send(
            Request::METHOD_POST,
            $this->path($path),
            configure: fn (PendingRequest $client): PendingRequest => $client
                ->withBody($contents, $contentType)
                ->withQueryParameters([...$params, 'name' => $filename]),
        );

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Immediately DELETE a record. When $dryRun is true Weclapp checks whether
     * the record could be deleted without deleting it.
     *
     * @throws RequestException
     */
    public function delete(string $endpoint, string|int $id, bool $dryRun = false): bool
    {
        $response = $this->send(Request::METHOD_DELETE, $this->recordPath($endpoint, $id), configure: $this->writeQuery($dryRun, false));

        $response->throw();

        return $response->successful();
    }

    /**
     * `dryRun` has Weclapp validate without persisting; `ignoreMissingProperties`
     * turns the full-replace PUT into one that changes only the given fields.
     *
     * @return (Closure(PendingRequest): PendingRequest)|null
     */
    private function writeQuery(bool $dryRun, bool $partial): ?Closure
    {
        $query = array_filter(['dryRun' => $dryRun, 'ignoreMissingProperties' => $partial]);

        return $query === []
            ? null
            : fn (PendingRequest $client): PendingRequest => $client->withQueryParameters(array_map(fn (): string => 'true', $query));
    }

    /**
     * The path addressing a single record.
     *
     * Weclapp addresses records as /{resource}/id/{id}; a bare
     * /{resource}/{id} does not exist for any resource in the v2 API.
     */
    public function recordPath(string $endpoint, string|int $id): string
    {
        return $this->path($endpoint).'/id/'.$id;
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

    public function accountingTransactions(): AccountingTransaction
    {
        return app(AccountingTransaction::class);
    }

    public function approvals(): Approval
    {
        return app(Approval::class);
    }

    public function approvalGroups(): ApprovalGroup
    {
        return app(ApprovalGroup::class);
    }

    public function approvalRules(): ApprovalRule
    {
        return app(ApprovalRule::class);
    }

    public function archivedEmails(): ArchivedEmail
    {
        return app(ArchivedEmail::class);
    }

    public function articleAccountingCodes(): ArticleAccountingCode
    {
        return app(ArticleAccountingCode::class);
    }

    public function articleCategoryClassifications(): ArticleCategoryClassification
    {
        return app(ArticleCategoryClassification::class);
    }

    public function articleItemGroups(): ArticleItemGroup
    {
        return app(ArticleItemGroup::class);
    }

    public function articlePrices(): ArticlePrice
    {
        return app(ArticlePrice::class);
    }

    public function articleRatings(): ArticleRating
    {
        return app(ArticleRating::class);
    }

    public function articleStatuses(): ArticleStatus
    {
        return app(ArticleStatus::class);
    }

    public function articleSupplySources(): ArticleSupplySource
    {
        return app(ArticleSupplySource::class);
    }

    public function attendances(): Attendance
    {
        return app(Attendance::class);
    }

    public function bankAccounts(): BankAccount
    {
        return app(BankAccount::class);
    }

    public function bankTransactions(): BankTransaction
    {
        return app(BankTransaction::class);
    }

    public function batchNumbers(): BatchNumber
    {
        return app(BatchNumber::class);
    }

    public function blanketPurchaseOrders(): BlanketPurchaseOrder
    {
        return app(BlanketPurchaseOrder::class);
    }

    public function calendars(): Calendar
    {
        return app(Calendar::class);
    }

    public function calendarEvents(): CalendarEvent
    {
        return app(CalendarEvent::class);
    }

    public function campaigns(): Campaign
    {
        return app(Campaign::class);
    }

    public function campaignParticipants(): CampaignParticipant
    {
        return app(CampaignParticipant::class);
    }

    public function cashAccounts(): CashAccount
    {
        return app(CashAccount::class);
    }

    public function cashAccountSheets(): CashAccountSheet
    {
        return app(CashAccountSheet::class);
    }

    public function cashAccountTransactions(): CashAccountTransaction
    {
        return app(CashAccountTransaction::class);
    }

    public function commercialLanguages(): CommercialLanguage
    {
        return app(CommercialLanguage::class);
    }

    public function companySizes(): CompanySize
    {
        return app(CompanySize::class);
    }

    public function contractAuthorizationUnits(): ContractAuthorizationUnit
    {
        return app(ContractAuthorizationUnit::class);
    }

    public function contractBillingGroups(): ContractBillingGroup
    {
        return app(ContractBillingGroup::class);
    }

    public function contractTerminationReasons(): ContractTerminationReason
    {
        return app(ContractTerminationReason::class);
    }

    public function contractTypes(): ContractType
    {
        return app(ContractType::class);
    }

    public function costCenterGroups(): CostCenterGroup
    {
        return app(CostCenterGroup::class);
    }

    public function costTypes(): CostType
    {
        return app(CostType::class);
    }

    public function crmCallCategories(): CrmCallCategory
    {
        return app(CrmCallCategory::class);
    }

    public function crmEvents(): CrmEvent
    {
        return app(CrmEvent::class);
    }

    public function crmEventCategories(): CrmEventCategory
    {
        return app(CrmEventCategory::class);
    }

    public function customAttributeDefinitions(): CustomAttributeDefinition
    {
        return app(CustomAttributeDefinition::class);
    }

    public function customerLeadLossReasons(): CustomerLeadLossReason
    {
        return app(CustomerLeadLossReason::class);
    }

    public function customerTopics(): CustomerTopic
    {
        return app(CustomerTopic::class);
    }

    public function customsTariffNumbers(): CustomsTariffNumber
    {
        return app(CustomsTariffNumber::class);
    }

    public function externalConnections(): ExternalConnection
    {
        return app(ExternalConnection::class);
    }

    public function financialYears(): FinancialYear
    {
        return app(FinancialYear::class);
    }

    public function fulfillmentProviders(): FulfillmentProvider
    {
        return app(FulfillmentProvider::class);
    }

    public function incomingGoods(): IncomingGoods
    {
        return app(IncomingGoods::class);
    }

    public function internalTransportReferences(): InternalTransportReference
    {
        return app(InternalTransportReference::class);
    }

    public function inventories(): Inventory
    {
        return app(Inventory::class);
    }

    public function inventoryGroups(): InventoryGroup
    {
        return app(InventoryGroup::class);
    }

    public function inventoryItems(): InventoryItem
    {
        return app(InventoryItem::class);
    }

    public function inventoryTransportReferences(): InventoryTransportReference
    {
        return app(InventoryTransportReference::class);
    }

    public function leadRatings(): LeadRating
    {
        return app(LeadRating::class);
    }

    public function legalForms(): LegalForm
    {
        return app(LegalForm::class);
    }

    public function loadingEquipmentIdentifiers(): LoadingEquipmentIdentifier
    {
        return app(LoadingEquipmentIdentifier::class);
    }

    public function mailTemplates(): MailTemplate
    {
        return app(MailTemplate::class);
    }

    public function manufacturers(): Manufacturer
    {
        return app(Manufacturer::class);
    }

    public function notifications(): Notification
    {
        return app(Notification::class);
    }

    public function numberRanges(): NumberRange
    {
        return app(NumberRange::class);
    }

    public function numberRangeValues(): NumberRangeValue
    {
        return app(NumberRangeValue::class);
    }

    public function opportunityTopics(): OpportunityTopic
    {
        return app(OpportunityTopic::class);
    }

    public function opportunityWinLossReasons(): OpportunityWinLossReason
    {
        return app(OpportunityWinLossReason::class);
    }

    public function partyRatings(): PartyRating
    {
        return app(PartyRating::class);
    }

    public function paymentRuns(): PaymentRun
    {
        return app(PaymentRun::class);
    }

    public function paymentRunItems(): PaymentRunItem
    {
        return app(PaymentRunItem::class);
    }

    public function performanceRecords(): PerformanceRecord
    {
        return app(PerformanceRecord::class);
    }

    public function personDepartments(): PersonDepartment
    {
        return app(PersonDepartment::class);
    }

    public function personRoles(): PersonRole
    {
        return app(PersonRole::class);
    }

    public function personalAccountingCodes(): PersonalAccountingCode
    {
        return app(PersonalAccountingCode::class);
    }

    public function picks(): Pick
    {
        return app(Pick::class);
    }

    public function pickCheckReasons(): PickCheckReason
    {
        return app(PickCheckReason::class);
    }

    public function placesOfService(): PlaceOfService
    {
        return app(PlaceOfService::class);
    }

    public function priceCalculationParameters(): PriceCalculationParameter
    {
        return app(PriceCalculationParameter::class);
    }

    public function productionOrders(): ProductionOrder
    {
        return app(ProductionOrder::class);
    }

    public function productionWorkSchedules(): ProductionWorkSchedule
    {
        return app(ProductionWorkSchedule::class);
    }

    public function productionWorkScheduleAssignments(): ProductionWorkScheduleAssignment
    {
        return app(ProductionWorkScheduleAssignment::class);
    }

    public function projectOrderStatusPages(): ProjectOrderStatusPage
    {
        return app(ProjectOrderStatusPage::class);
    }

    public function purchaseOpenItems(): PurchaseOpenItem
    {
        return app(PurchaseOpenItem::class);
    }

    public function purchaseOrderRequests(): PurchaseOrderRequest
    {
        return app(PurchaseOrderRequest::class);
    }

    public function purchaseRequisitions(): PurchaseRequisition
    {
        return app(PurchaseRequisition::class);
    }

    public function rebates(): Rebate
    {
        return app(Rebate::class);
    }

    public function recordEmailingRules(): RecordEmailingRule
    {
        return app(RecordEmailingRule::class);
    }

    public function regions(): Region
    {
        return app(Region::class);
    }

    public function reminders(): Reminder
    {
        return app(Reminder::class);
    }

    public function remotePrintJobs(): RemotePrintJob
    {
        return app(RemotePrintJob::class);
    }

    public function salesOpenItems(): SalesOpenItem
    {
        return app(SalesOpenItem::class);
    }

    public function salesTeams(): SalesTeam
    {
        return app(SalesTeam::class);
    }

    public function sectors(): Sector
    {
        return app(Sector::class);
    }

    public function sepaDirectDebitMandates(): SepaDirectDebitMandate
    {
        return app(SepaDirectDebitMandate::class);
    }

    public function serialNumbers(): SerialNumber
    {
        return app(SerialNumber::class);
    }

    public function serviceQuotas(): ServiceQuota
    {
        return app(ServiceQuota::class);
    }

    public function shelves(): Shelf
    {
        return app(Shelf::class);
    }

    public function shipmentMethods(): ShipmentMethod
    {
        return app(ShipmentMethod::class);
    }

    public function shipmentReturnAssessments(): ShipmentReturnAssessment
    {
        return app(ShipmentReturnAssessment::class);
    }

    public function shipmentReturnErrors(): ShipmentReturnError
    {
        return app(ShipmentReturnError::class);
    }

    public function shipmentReturnReasons(): ShipmentReturnReason
    {
        return app(ShipmentReturnReason::class);
    }

    public function shipmentReturnRectifications(): ShipmentReturnRectification
    {
        return app(ShipmentReturnRectification::class);
    }

    public function shippingCarriers(): ShippingCarrier
    {
        return app(ShippingCarrier::class);
    }

    public function storageLocations(): StorageLocation
    {
        return app(StorageLocation::class);
    }

    public function storagePlaces(): StoragePlace
    {
        return app(StoragePlace::class);
    }

    public function storagePlaceBlockingReasons(): StoragePlaceBlockingReason
    {
        return app(StoragePlaceBlockingReason::class);
    }

    public function storagePlaceSizes(): StoragePlaceSize
    {
        return app(StoragePlaceSize::class);
    }

    public function tags(): Tag
    {
        return app(Tag::class);
    }

    public function tasks(): Task
    {
        return app(Task::class);
    }

    public function taskLists(): TaskList
    {
        return app(TaskList::class);
    }

    public function taskTemplates(): TaskTemplate
    {
        return app(TaskTemplate::class);
    }

    public function taxDeterminationRules(): TaxDeterminationRule
    {
        return app(TaxDeterminationRule::class);
    }

    public function tickets(): Ticket
    {
        return app(Ticket::class);
    }

    public function ticketAssignmentRules(): TicketAssignmentRule
    {
        return app(TicketAssignmentRule::class);
    }

    public function ticketCategories(): TicketCategory
    {
        return app(TicketCategory::class);
    }

    public function ticketChannels(): TicketChannel
    {
        return app(TicketChannel::class);
    }

    public function ticketFaqs(): TicketFaq
    {
        return app(TicketFaq::class);
    }

    public function ticketPoolingGroups(): TicketPoolingGroup
    {
        return app(TicketPoolingGroup::class);
    }

    public function ticketPriorities(): TicketPriority
    {
        return app(TicketPriority::class);
    }

    public function ticketServiceLevelAgreements(): TicketServiceLevelAgreement
    {
        return app(TicketServiceLevelAgreement::class);
    }

    public function ticketStatuses(): TicketStatus
    {
        return app(TicketStatus::class);
    }

    public function ticketTypes(): TicketType
    {
        return app(TicketType::class);
    }

    public function timeRecords(): TimeRecord
    {
        return app(TimeRecord::class);
    }

    public function titles(): Title
    {
        return app(Title::class);
    }

    public function translations(): Translation
    {
        return app(Translation::class);
    }

    public function transportationOrders(): TransportationOrder
    {
        return app(TransportationOrder::class);
    }

    public function userRoles(): UserRole
    {
        return app(UserRole::class);
    }

    public function variantArticles(): VariantArticle
    {
        return app(VariantArticle::class);
    }

    public function variantArticleAttributes(): VariantArticleAttribute
    {
        return app(VariantArticleAttribute::class);
    }

    public function variantArticleVariants(): VariantArticleVariant
    {
        return app(VariantArticleVariant::class);
    }

    public function warehouseStocks(): WarehouseStock
    {
        return app(WarehouseStock::class);
    }

    public function warehouseStockMovements(): WarehouseStockMovement
    {
        return app(WarehouseStockMovement::class);
    }

    public function weclappOs(): WeclappOs
    {
        return app(WeclappOs::class);
    }

    public function workScheduleProfiles(): WorkScheduleProfile
    {
        return app(WorkScheduleProfile::class);
    }

    protected function path(string $endpoint): string
    {
        return ltrim($endpoint, '/');
    }
}
