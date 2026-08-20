<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Mindtwo\LaravelWeclappApi\Commands\WeclappSyncCommand;
use Mindtwo\LaravelWeclappApi\Commands\WeclappUpdateCommand;
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
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Endpoint;
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
use Mindtwo\LaravelWeclappApi\Listeners\LogWeclappEvent;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WeclappApiServiceProvider extends PackageServiceProvider
{
    /**
     * Mirror-table migrations, keyed by the publish tag suffix. Each entry gets
     * its own `weclapp-api-migrations-{key}` tag so a consumer can take only the
     * tables it reads instead of all of them.
     *
     * Keys match the Sync\SyncRegistry entity names, except `parties`, which
     * backs both the `customers` and `suppliers` entities.
     *
     * @var array<string, string>
     */
    private const array MIGRATIONS = [
        'parties'            => '2026_04_20_100001_create_weclapp_parties_table.php',
        'article-categories' => '2026_04_20_100002_create_weclapp_article_categories_table.php',
        'articles'           => '2026_04_20_100003_create_weclapp_articles_table.php',
        'users'              => '2026_04_20_100004_create_weclapp_users_table.php',
        'quotations'         => '2026_04_20_100005_create_weclapp_quotations_table.php',
        'sales-orders'       => '2026_04_20_100007_create_weclapp_sales_orders_table.php',
        'projects'           => '2026_04_20_100009_create_weclapp_projects_table.php',
        'article-prices'     => '2026_04_20_100010_create_weclapp_article_prices_table.php',
        'sales-invoices'     => '2026_04_20_100011_create_weclapp_sales_invoices_table.php',
    ];

    /**
     * Every typed endpoint class, registered as a singleton and reachable via
     * the matching WeclappClient accessor.
     *
     * @var list<class-string<Endpoint>>
     */
    private const array ENDPOINTS = [
        AccountingTransaction::class,
        Approval::class,
        ApprovalGroup::class,
        ApprovalRule::class,
        ArchivedEmail::class,
        Article::class,
        ArticleAccountingCode::class,
        ArticleCategory::class,
        ArticleCategoryClassification::class,
        ArticleItemGroup::class,
        ArticlePrice::class,
        ArticleRating::class,
        ArticleStatus::class,
        ArticleSupplySource::class,
        Attendance::class,
        BankAccount::class,
        BankTransaction::class,
        BatchNumber::class,
        BlanketPurchaseOrder::class,
        BlanketSalesOrder::class,
        Calendar::class,
        CalendarEvent::class,
        Campaign::class,
        CampaignParticipant::class,
        CashAccount::class,
        CashAccountSheet::class,
        CashAccountTransaction::class,
        Comment::class,
        CommercialLanguage::class,
        CompanySize::class,
        Contract::class,
        ContractAuthorizationUnit::class,
        ContractBillingGroup::class,
        ContractTerminationReason::class,
        ContractType::class,
        CostCenter::class,
        CostCenterGroup::class,
        CostType::class,
        CrmCallCategory::class,
        CrmEvent::class,
        CrmEventCategory::class,
        Currency::class,
        CustomAttributeDefinition::class,
        Customer::class,
        CustomerCategory::class,
        CustomerLeadLossReason::class,
        CustomerTopic::class,
        CustomsTariffNumber::class,
        Document::class,
        ExternalConnection::class,
        FinancialYear::class,
        FulfillmentProvider::class,
        IncomingGoods::class,
        InternalTransportReference::class,
        Inventory::class,
        InventoryGroup::class,
        InventoryItem::class,
        InventoryTransportReference::class,
        LeadRating::class,
        LeadSource::class,
        LedgerAccount::class,
        LegalForm::class,
        LoadingEquipmentIdentifier::class,
        MailTemplate::class,
        Manufacturer::class,
        Notification::class,
        NumberRange::class,
        NumberRangeValue::class,
        Opportunity::class,
        OpportunityTopic::class,
        OpportunityWinLossReason::class,
        Party::class,
        PartyRating::class,
        PaymentMethod::class,
        PaymentRun::class,
        PaymentRunItem::class,
        PerformanceRecord::class,
        PersonDepartment::class,
        PersonRole::class,
        PersonalAccountingCode::class,
        Pick::class,
        PickCheckReason::class,
        PlaceOfService::class,
        PriceCalculationParameter::class,
        ProductionOrder::class,
        ProductionWorkSchedule::class,
        ProductionWorkScheduleAssignment::class,
        Project::class,
        ProjectOrderStatusPage::class,
        PurchaseInvoice::class,
        PurchaseOpenItem::class,
        PurchaseOrder::class,
        PurchaseOrderRequest::class,
        PurchaseRequisition::class,
        Quotation::class,
        Rebate::class,
        RecordEmailingRule::class,
        Region::class,
        Reminder::class,
        RemotePrintJob::class,
        SalesInvoice::class,
        SalesOpenItem::class,
        SalesOrder::class,
        SalesStage::class,
        SalesTeam::class,
        Sector::class,
        SepaDirectDebitMandate::class,
        SerialNumber::class,
        ServiceQuota::class,
        Shelf::class,
        Shipment::class,
        ShipmentMethod::class,
        ShipmentReturnAssessment::class,
        ShipmentReturnError::class,
        ShipmentReturnReason::class,
        ShipmentReturnRectification::class,
        ShippingCarrier::class,
        StorageLocation::class,
        StoragePlace::class,
        StoragePlaceBlockingReason::class,
        StoragePlaceSize::class,
        Supplier::class,
        Tag::class,
        Task::class,
        TaskList::class,
        TaskTemplate::class,
        Tax::class,
        TaxDeterminationRule::class,
        TermOfPayment::class,
        Ticket::class,
        TicketAssignmentRule::class,
        TicketCategory::class,
        TicketChannel::class,
        TicketFaq::class,
        TicketPoolingGroup::class,
        TicketPriority::class,
        TicketServiceLevelAgreement::class,
        TicketStatus::class,
        TicketType::class,
        TimeRecord::class,
        Title::class,
        Translation::class,
        TransportationOrder::class,
        Unit::class,
        User::class,
        UserRole::class,
        VariantArticle::class,
        VariantArticleAttribute::class,
        VariantArticleVariant::class,
        Warehouse::class,
        WarehouseStock::class,
        WarehouseStockMovement::class,
        Webhook::class,
        WeclappOs::class,
        WorkScheduleProfile::class,
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
            ->hasConfigFile()
            ->hasCommand(WeclappSyncCommand::class)
            ->hasCommand(WeclappUpdateCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(WeclappClient::class);

        foreach (self::ENDPOINTS as $endpoint) {
            $this->app->singleton($endpoint);
        }
    }

    public function packageBooted(): void
    {
        // Publish (rather than auto-run) so a consuming app opts in when ready,
        // and must not collide with an app's own weclapp tables.
        //
        // Two granularities: `weclapp-api-migrations` publishes every mirror
        // table, and one tag per entity publishes just that table. Consumers
        // rarely want all of them -- take only the entities you actually read.
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'weclapp-api-migrations');

        foreach (self::MIGRATIONS as $entity => $migration) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations/'.$migration => database_path('migrations/'.$migration),
            ], 'weclapp-api-migrations-'.$entity);
        }

        RateLimiter::for('weclapp-api-jobs', function (): Limit {
            return Limit::perMinute((int) config('weclapp-api.rate_limit_per_minute', 100))->by('weclapp-api-jobs');
        });

        if (config('weclapp-api.logging.enabled', false)) {
            Event::listen(WeclappApiCallCompleted::class, LogWeclappEvent::class);
        }
    }
}
