<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Models\ArticlePrice;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Project;
use Mindtwo\LaravelWeclappApi\Models\Quotation;
use Mindtwo\LaravelWeclappApi\Models\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;
use Mindtwo\LaravelWeclappApi\Models\User;

/**
 * The set of Weclapp entities the package can sync directly from an endpoint
 * into a mirror table. Derived data (amounts, reports) and nested collections
 * (addresses, contacts, bank accounts) are intentionally left to the consumer.
 *
 * Field maps validated against docs/specifications/weclapp-openapi_v2.json and a
 * live read of every endpoint below (500-record sample where available); the
 * collection envelope is `{ "result": [...] }`. Every field mapped here was
 * observed on at least one live record.
 *
 * Customers and suppliers are both filtered views of /party — /customer and
 * /supplier return 404. The `project` endpoint is absent from the spec but is
 * real and populated (live-confirmed), so it is mapped from live responses.
 */
final class SyncRegistry
{
    /**
     * @return array<string, SyncDefinition>
     */
    public static function all(): array
    {
        return [
            // customers and suppliers are the only two definitions sharing a model,
            // and that is exactly why neither reconciles: each fetches its own
            // filtered slice of /party, so letting either archive "everything I did
            // not see" would wipe the other's rows on every run. Every other
            // definition below owns its table outright and can reconcile safely.
            'customers' => new SyncDefinition(
                endpoint: 'party',
                model: Party::class,
                map: [
                    'company'             => 'company',
                    'company_2'           => 'company2',
                    'customer_number'     => 'customerNumber',
                    'email'               => 'email',
                    'party_type'          => 'partyType',
                    'phone'               => 'phone',
                    'responsible_user_id' => 'responsibleUserId',
                    'sector_id'           => 'sectorId',
                    'website'             => 'website',
                    'weclapp_id'          => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                filters: ['customer-eq' => 'true'],
            ),
            'suppliers' => new SyncDefinition(
                endpoint: 'party',
                model: Party::class,
                map: [
                    'company'         => 'company',
                    'description'     => 'description',
                    'email'           => 'email',
                    'first_name'      => 'firstName',
                    'last_name'       => 'lastName',
                    'party_type'      => 'partyType',
                    'phone'           => 'phone',
                    'salutation'      => 'salutation',
                    'supplier_number' => 'supplierNumber',
                    'weclapp_id'      => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                filters: ['supplier-eq' => 'true'],
            ),
            'article-categories' => new SyncDefinition(
                endpoint: 'articleCategory',
                model: ArticleCategory::class,
                map: [
                    'name'       => 'name',
                    'weclapp_id' => 'id',
                ],
                reconciles: true,
            ),
            'articles' => new SyncDefinition(
                endpoint: 'article',
                model: Article::class,
                map: [
                    'active'              => 'active',
                    'article_category_id' => 'articleCategoryId',
                    'article_number'      => 'articleNumber',
                    'description'         => 'description',
                    'name'                => 'name',
                    'unit_id'             => 'unitId',
                    'weclapp_id'          => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                reconciles: true,
            ),
            'users' => new SyncDefinition(
                endpoint: 'user',
                model: User::class,
                map: [
                    'email'      => 'email',
                    'first_name' => 'firstName',
                    'last_name'  => 'lastName',
                    'weclapp_id' => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                reconciles: true,
            ),
            'quotations' => new SyncDefinition(
                endpoint: 'quotation',
                model: Quotation::class,
                map: [
                    'customer_id'      => 'customerId',
                    'gross_amount'     => 'grossAmount',
                    'net_amount'       => 'netAmount',
                    'quotation_number' => 'quotationNumber',
                    'status'           => 'status',
                    'version'          => 'quotationVersion',
                    'weclapp_id'       => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                reconciles: true,
            ),
            'sales-orders' => new SyncDefinition(
                endpoint: 'salesOrder',
                model: SalesOrder::class,
                map: [
                    'customer_id'         => 'customerId',
                    'gross_amount'        => 'grossAmount',
                    'net_amount'          => 'netAmount',
                    'order_number'        => 'orderNumber',
                    'quotation_id'        => 'quotationId',
                    'record_free_text'    => 'recordFreeText',
                    'responsible_user_id' => 'responsibleUserId',
                    'status'              => 'status',
                    'version'             => 'version',
                    'weclapp_id'          => 'id',
                ],
                dates: [
                    'last_modified'       => 'lastModifiedDate',
                    'order_date'          => 'orderDate',
                    'pricing_date'        => 'pricingDate',
                    'service_period_from' => 'servicePeriodFrom',
                    'service_period_to'   => 'servicePeriodTo',
                ],
                reconciles: true,
            ),
            'projects' => new SyncDefinition(
                endpoint: 'project',
                model: Project::class,
                map: [
                    'customer_id'    => 'customerId',
                    'description'    => 'description',
                    'project_number' => 'projectNumber',
                    'status'         => 'status',
                    'title'          => 'name',
                    'weclapp_id'     => 'id',
                ],
                dates: [
                    'last_modified'      => 'lastModifiedDate',
                    'project_start_date' => 'projectStartDate',
                ],
                reconciles: true,
            ),
            // customerId is present only on customer-specific prices; Weclapp omits
            // null fields entirely, so the synchronizer leaves the column null for
            // list prices.
            //
            // reductionAdditions is a nested collection, but unlike the other nested
            // collections it changes the price, so it is flattened rather than
            // skipped: a live full read found it on 358 of 967 rows, 354 of them on
            // customer-specific prices, and never more than one entry per row.
            // Dropping it would mirror the wrong price for 96% of customer overrides.
            'article-prices' => new SyncDefinition(
                endpoint: 'articlePrice',
                model: ArticlePrice::class,
                map: [
                    'article_id'               => 'articleId',
                    'currency_id'              => 'currencyId',
                    'customer_id'              => 'customerId',
                    'description'              => 'description',
                    'last_modified_by_user_id' => 'lastModifiedByUserId',
                    'price'                    => 'price',
                    'price_scale_type'         => 'priceScaleType',
                    'price_scale_value'        => 'priceScaleValue',
                    'sales_channel'            => 'salesChannel',
                    'version'                  => 'version',
                    'weclapp_id'               => 'id',
                ],
                dates: [
                    'end_date'      => 'endDate',
                    'last_modified' => 'lastModifiedDate',
                    'start_date'    => 'startDate',
                ],
                paths: [
                    'reduction_type'  => 'reductionAdditions.0.type',
                    'reduction_value' => 'reductionAdditions.0.value',
                ],
                reconciles: true,
            ),
            'sales-invoices' => new SyncDefinition(
                endpoint: 'salesInvoice',
                model: SalesInvoice::class,
                map: [
                    'creator_id'          => 'creatorId',
                    'currency_id'         => 'recordCurrencyId',
                    'customer_id'         => 'customerId',
                    'description'         => 'description',
                    'gross_amount'        => 'grossAmount',
                    'invoice_number'      => 'invoiceNumber',
                    'net_amount'          => 'netAmount',
                    'paid'                => 'paid',
                    'payment_method_id'   => 'paymentMethodId',
                    'payment_status'      => 'paymentStatus',
                    'record_free_text'    => 'recordFreeText',
                    'responsible_user_id' => 'responsibleUserId',
                    'sales_channel'       => 'salesChannel',
                    'sales_invoice_type'  => 'salesInvoiceType',
                    'sales_order_id'      => 'salesOrderId',
                    'status'              => 'status',
                    'term_of_payment_id'  => 'termOfPaymentId',
                    'version'             => 'version',
                    'weclapp_id'          => 'id',
                ],
                dates: [
                    'invoice_date'        => 'invoiceDate',
                    'last_modified'       => 'lastModifiedDate',
                    'pricing_date'        => 'pricingDate',
                    'service_period_from' => 'servicePeriodFrom',
                    'service_period_to'   => 'servicePeriodTo',
                    'shipping_date'       => 'shippingDate',
                ],
                reconciles: true,
            ),
        ];
    }
}
