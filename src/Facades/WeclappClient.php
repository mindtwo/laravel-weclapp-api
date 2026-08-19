<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Facades;

use Illuminate\Support\Facades\Facade;
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
use Mindtwo\LaravelWeclappApi\WeclappClient as BaseWeclappClient;

/**
 * @method static \Illuminate\Support\Collection<int, object> get(string $endpoint, array<string, mixed> $params = [])
 * @method static object|null find(string $endpoint, string|int $id)
 * @method static int count(string $endpoint, array<string, mixed> $params = [])
 * @method static array<string, mixed> post(string $endpoint, array<string, mixed> $data, bool $dryRun = false)
 * @method static array<string, mixed> put(string $endpoint, string|int $id, array<string, mixed> $data)
 * @method static bool delete(string $endpoint, string|int $id)
 * @method static string recordPath(string $endpoint, string|int $id)
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
 * @method static AccountingTransaction accountingTransactions()
 * @method static Approval approvals()
 * @method static ApprovalGroup approvalGroups()
 * @method static ApprovalRule approvalRules()
 * @method static ArchivedEmail archivedEmails()
 * @method static ArticleAccountingCode articleAccountingCodes()
 * @method static ArticleCategoryClassification articleCategoryClassifications()
 * @method static ArticleItemGroup articleItemGroups()
 * @method static ArticlePrice articlePrices()
 * @method static ArticleRating articleRatings()
 * @method static ArticleStatus articleStatuses()
 * @method static ArticleSupplySource articleSupplySources()
 * @method static Attendance attendances()
 * @method static BankAccount bankAccounts()
 * @method static BankTransaction bankTransactions()
 * @method static BatchNumber batchNumbers()
 * @method static BlanketPurchaseOrder blanketPurchaseOrders()
 * @method static Calendar calendars()
 * @method static CalendarEvent calendarEvents()
 * @method static Campaign campaigns()
 * @method static CampaignParticipant campaignParticipants()
 * @method static CashAccount cashAccounts()
 * @method static CashAccountSheet cashAccountSheets()
 * @method static CashAccountTransaction cashAccountTransactions()
 * @method static CommercialLanguage commercialLanguages()
 * @method static CompanySize companySizes()
 * @method static ContractAuthorizationUnit contractAuthorizationUnits()
 * @method static ContractBillingGroup contractBillingGroups()
 * @method static ContractTerminationReason contractTerminationReasons()
 * @method static ContractType contractTypes()
 * @method static CostCenterGroup costCenterGroups()
 * @method static CostType costTypes()
 * @method static CrmCallCategory crmCallCategories()
 * @method static CrmEvent crmEvents()
 * @method static CrmEventCategory crmEventCategories()
 * @method static CustomAttributeDefinition customAttributeDefinitions()
 * @method static CustomerLeadLossReason customerLeadLossReasons()
 * @method static CustomerTopic customerTopics()
 * @method static CustomsTariffNumber customsTariffNumbers()
 * @method static ExternalConnection externalConnections()
 * @method static FinancialYear financialYears()
 * @method static FulfillmentProvider fulfillmentProviders()
 * @method static IncomingGoods incomingGoods()
 * @method static InternalTransportReference internalTransportReferences()
 * @method static Inventory inventories()
 * @method static InventoryGroup inventoryGroups()
 * @method static InventoryItem inventoryItems()
 * @method static InventoryTransportReference inventoryTransportReferences()
 * @method static LeadRating leadRatings()
 * @method static LegalForm legalForms()
 * @method static LoadingEquipmentIdentifier loadingEquipmentIdentifiers()
 * @method static MailTemplate mailTemplates()
 * @method static Manufacturer manufacturers()
 * @method static Notification notifications()
 * @method static NumberRange numberRanges()
 * @method static NumberRangeValue numberRangeValues()
 * @method static OpportunityTopic opportunityTopics()
 * @method static OpportunityWinLossReason opportunityWinLossReasons()
 * @method static PartyRating partyRatings()
 * @method static PaymentRun paymentRuns()
 * @method static PaymentRunItem paymentRunItems()
 * @method static PerformanceRecord performanceRecords()
 * @method static PersonDepartment personDepartments()
 * @method static PersonRole personRoles()
 * @method static PersonalAccountingCode personalAccountingCodes()
 * @method static Pick picks()
 * @method static PickCheckReason pickCheckReasons()
 * @method static PlaceOfService placesOfService()
 * @method static PriceCalculationParameter priceCalculationParameters()
 * @method static ProductionOrder productionOrders()
 * @method static ProductionWorkSchedule productionWorkSchedules()
 * @method static ProductionWorkScheduleAssignment productionWorkScheduleAssignments()
 * @method static ProjectOrderStatusPage projectOrderStatusPages()
 * @method static PurchaseOpenItem purchaseOpenItems()
 * @method static PurchaseOrderRequest purchaseOrderRequests()
 * @method static PurchaseRequisition purchaseRequisitions()
 * @method static Rebate rebates()
 * @method static RecordEmailingRule recordEmailingRules()
 * @method static Region regions()
 * @method static Reminder reminders()
 * @method static RemotePrintJob remotePrintJobs()
 * @method static SalesOpenItem salesOpenItems()
 * @method static SalesTeam salesTeams()
 * @method static Sector sectors()
 * @method static SepaDirectDebitMandate sepaDirectDebitMandates()
 * @method static SerialNumber serialNumbers()
 * @method static ServiceQuota serviceQuotas()
 * @method static Shelf shelves()
 * @method static ShipmentMethod shipmentMethods()
 * @method static ShipmentReturnAssessment shipmentReturnAssessments()
 * @method static ShipmentReturnError shipmentReturnErrors()
 * @method static ShipmentReturnReason shipmentReturnReasons()
 * @method static ShipmentReturnRectification shipmentReturnRectifications()
 * @method static ShippingCarrier shippingCarriers()
 * @method static StorageLocation storageLocations()
 * @method static StoragePlace storagePlaces()
 * @method static StoragePlaceBlockingReason storagePlaceBlockingReasons()
 * @method static StoragePlaceSize storagePlaceSizes()
 * @method static Tag tags()
 * @method static Task tasks()
 * @method static TaskList taskLists()
 * @method static TaskTemplate taskTemplates()
 * @method static TaxDeterminationRule taxDeterminationRules()
 * @method static Ticket tickets()
 * @method static TicketAssignmentRule ticketAssignmentRules()
 * @method static TicketCategory ticketCategories()
 * @method static TicketChannel ticketChannels()
 * @method static TicketFaq ticketFaqs()
 * @method static TicketPoolingGroup ticketPoolingGroups()
 * @method static TicketPriority ticketPriorities()
 * @method static TicketServiceLevelAgreement ticketServiceLevelAgreements()
 * @method static TicketStatus ticketStatuses()
 * @method static TicketType ticketTypes()
 * @method static TimeRecord timeRecords()
 * @method static Title titles()
 * @method static Translation translations()
 * @method static TransportationOrder transportationOrders()
 * @method static UserRole userRoles()
 * @method static VariantArticle variantArticles()
 * @method static VariantArticleAttribute variantArticleAttributes()
 * @method static VariantArticleVariant variantArticleVariants()
 * @method static WarehouseStock warehouseStocks()
 * @method static WarehouseStockMovement warehouseStockMovements()
 * @method static WeclappOs weclappOs()
 * @method static WorkScheduleProfile workScheduleProfiles()
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
