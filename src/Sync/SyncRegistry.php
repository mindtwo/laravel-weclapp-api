<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Project;
use Mindtwo\LaravelWeclappApi\Models\Quotation;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;
use Mindtwo\LaravelWeclappApi\Models\User;

/**
 * The set of Weclapp entities the package can sync directly from an endpoint
 * into a mirror table. Derived data (amounts, reports) and nested collections
 * (addresses, contacts, bank accounts) are intentionally left to the consumer.
 *
 * Field maps validated against the official Weclapp OpenAPI 3.1 spec
 * (https://www.weclapp.com/api/openapi_v2.yaml) and a live read against the
 * production API; the collection envelope is `{ "result": [...] }`. Note: the
 * `project` endpoint is absent from the public spec but is real and live
 * (production-confirmed).
 */
final class SyncRegistry
{
    /**
     * @return array<string, SyncDefinition>
     */
    public static function all(): array
    {
        return [
            'customers' => new SyncDefinition(
                endpoint: 'customer',
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
            ),
            'suppliers' => new SyncDefinition(
                endpoint: 'supplier',
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
            ),
            'article-categories' => new SyncDefinition(
                endpoint: 'articleCategory',
                model: ArticleCategory::class,
                map: [
                    'name'       => 'name',
                    'weclapp_id' => 'id',
                ],
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
            ),
            'quotations' => new SyncDefinition(
                endpoint: 'quotation',
                model: Quotation::class,
                map: [
                    'customer_id'      => 'customerId',
                    'customer_number'  => 'customerNumber',
                    'gross_amount'     => 'grossAmount',
                    'net_amount'       => 'netAmount',
                    'quotation_number' => 'quotationNumber',
                    'status'           => 'status',
                    'version'          => 'quotationVersion',
                    'weclapp_id'       => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
            ),
            'sales-orders' => new SyncDefinition(
                endpoint: 'salesOrder',
                model: SalesOrder::class,
                map: [
                    'customer_id'         => 'customerId',
                    'customer_number'     => 'customerNumber',
                    'gross_amount'        => 'grossAmount',
                    'net_amount'          => 'netAmount',
                    'order_number'        => 'orderNumber',
                    'quotation_id'        => 'quotationId',
                    'quotation_number'    => 'quotationNumber',
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
            ),
            'projects' => new SyncDefinition(
                endpoint: 'project',
                model: Project::class,
                map: [
                    'customer_id'     => 'customerId',
                    'customer_number' => 'customerNumber',
                    'description'     => 'description',
                    'project_number'  => 'projectNumber',
                    'status'          => 'status',
                    'title'           => 'name',
                    'weclapp_id'      => 'id',
                ],
                dates: [
                    'last_modified'      => 'lastModifiedDate',
                    'project_start_date' => 'projectStartDate',
                ],
            ),
        ];
    }
}
