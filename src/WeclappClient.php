<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Immediately PUT data to a record, replacing it.
     *
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     *
     * @return array<string, mixed>
     */
    public function put(string $endpoint, string|int $id, array $data): array
    {
        if ($this->writesSuppressed()) {
            $this->recordSuppressedWrite('PUT', $endpoint);

            return [];
        }

        $response = $this->client()->put($this->recordPath($endpoint, $id), $data);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Immediately DELETE a record.
     *
     * @throws RequestException
     */
    public function delete(string $endpoint, string|int $id): bool
    {
        if ($this->writesSuppressed()) {
            $this->recordSuppressedWrite('DELETE', $endpoint);

            return true;
        }

        $response = $this->client()->delete($this->recordPath($endpoint, $id));

        $response->throw();

        return $response->successful();
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
