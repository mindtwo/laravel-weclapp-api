<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Illuminate\Support\Facades\Log;
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
 * Customers and suppliers are one resource, /party, told apart by its `customer`
 * and `supplier` flags — /customer and /supplier return 404. The `project`
 * endpoint is absent from the spec but is real and populated (live-confirmed),
 * so it is mapped from live responses.
 */
final class SyncRegistry
{
    /**
     * @return array<string, SyncDefinition>
     */
    public static function all(): array
    {
        return [
            // One definition for the whole of /party rather than a filtered one per
            // role: a party is a customer, a supplier, both or neither, and only an
            // unfiltered read can see a role being dropped. Owning the table outright
            // is also what lets it reconcile.
            'parties' => new SyncDefinition(
                endpoint: 'party',
                model: Party::class,
                map: [
                    'company'             => 'company',
                    'company_2'           => 'company2',
                    'customer_number'     => 'customerNumber',
                    'description'         => 'description',
                    'email'               => 'email',
                    'first_name'          => 'firstName',
                    'last_name'           => 'lastName',
                    'party_type'          => 'partyType',
                    'phone'               => 'phone',
                    'responsible_user_id' => 'responsibleUserId',
                    'salutation'          => 'salutation',
                    'sector_id'           => 'sectorId',
                    'supplier_number'     => 'supplierNumber',
                    'website'             => 'website',
                    'weclapp_id'          => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                // Weclapp sends the flags on every party, false included; the column
                // default only covers a minimal fixture.
                derive: [
                    'is_customer' => fn (object $record): bool => (bool) ($record->customer ?? false),
                    'is_supplier' => fn (object $record): bool => (bool) ($record->supplier ?? false),
                ],
                reconciles: true,
            ),
            'article-categories' => new SyncDefinition(
                endpoint: 'articleCategory',
                model: ArticleCategory::class,
                map: [
                    'name'       => 'name',
                    'weclapp_id' => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                reconciles: true,
            ),
            // articleImages is a nested collection and the rest of them are skipped,
            // but the main image is flattened for the same reason reductionAdditions
            // is: a consumer has to be able to answer "does Weclapp have an image for
            // this article?" across many articles at once, and without these two
            // columns that question costs one API call per article. A live full read
            // found articleImages on 3 of 748 articles, so the answer is almost
            // always no — which is exactly why it has to be cheap to ask.
            //
            // `tags` is flattened for the opposite reason: it is dense. A consumer
            // marks the articles that belong in its application with one Weclapp tag,
            // named in `weclapp-api.visible_tag`, so the mirror keeps the answer as a
            // boolean rather than the collection. With no tag configured every
            // article is visible. Every article is mirrored either way: filtering
            // the *sync* would soft-delete a row the moment somebody untagged an
            // article in Weclapp, and other tables reference these rows by
            // `weclapp_id`. Consumers filter at the point where an article is
            // offered, not at the point where it is stored.
            'articles' => new SyncDefinition(
                endpoint: 'article',
                model: Article::class,
                map: [
                    'article_category_id' => 'articleCategoryId',
                    'article_number'      => 'articleNumber',
                    'description'         => 'description',
                    // The second HTML text field. Mirrored because it is the Weclapp
                    // counterpart of a local field a consumer may want to push up, so
                    // the comparison has to be answerable without an API call.
                    'long_text'                => 'longText',
                    'name'                     => 'name',
                    'primary_supply_source_id' => 'primarySupplySourceId',
                    'short_description_1'      => 'shortDescription1',
                    'unit_id'                  => 'unitId',
                    'weclapp_id'               => 'id',
                ],
                dates: ['last_modified' => 'lastModifiedDate'],
                // Values are passed through raw, as the scalar maps do, and left to
                // the model casts to convert: Weclapp sends ids as strings, the
                // mirror stores them as integers.
                derive: [
                    // Weclapp sends `active` on every article, false included — the
                    // mirror holds explicit inactive rows. The column is NOT NULL with a
                    // default of true, so an absent key (a minimal fixture, never a live
                    // record) takes the column's default rather than a null it rejects.
                    'active'              => fn (object $record): bool => (bool) ($record->active ?? true),
                    'main_image_id'       => fn (object $record): mixed => self::mainImageField($record, 'id'),
                    'main_image_filename' => fn (object $record): mixed => self::mainImageField($record, 'fileName'),
                    'supply_source_count' => fn (object $record): mixed => self::countOf($record, 'supplySources'),
                    'visible'             => fn (object $record): bool => self::isVisible($record),
                ],
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
                derive: [
                    'reduction_type'  => fn (object $record): mixed => self::reductionField($record, 'type'),
                    'reduction_value' => fn (object $record): mixed => self::reductionField($record, 'value'),
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

    /**
     * How many entries a nested collection holds.
     *
     * Only the count is mirrored, not the collection: the consumer's question is
     * whether the collection is empty, because a non-empty `supplySources` makes the
     * article unwritable through the API.
     */
    private static function countOf(object $record, string $collection): int
    {
        $value = data_get($record, $collection);

        return is_countable($value) ? count($value) : 0;
    }

    /**
     * Whether the record's `tags` collection carries a given tag.
     *
     * Weclapp's `tags` is a free-text `array<string>` with no controlled
     * vocabulary, which is why this is a flat, case-sensitive membership test
     * rather than anything fuzzier: a mistyped tag has to read as absent, so it
     * shows up as a gap somebody notices instead of being silently forgiven.
     */
    private static function hasTag(object $record, string $tag): bool
    {
        $tags = data_get($record, 'tags');

        return is_iterable($tags) && in_array($tag, is_array($tags) ? $tags : iterator_to_array($tags), true);
    }

    /**
     * Whether the article belongs in the consuming application: it carries the
     * tag named in `weclapp-api.visible_tag`, or no tag is configured at all.
     */
    private static function isVisible(object $record): bool
    {
        $tag = config('weclapp-api.visible_tag');

        if (! is_string($tag) || $tag === '') {
            return true;
        }

        return self::hasTag($record, $tag);
    }

    /**
     * One field of the price's reductionAdditions entry, or null when there is
     * none.
     *
     * Every price seen live carries at most one entry, and the spec sets no
     * ordering and no maximum. Two columns can hold one entry, so the first is
     * taken and a second is reported rather than dropped in silence: a price
     * mirrored with only its reduction and not its surcharge is wrong in a way
     * nobody would notice.
     */
    private static function reductionField(object $record, string $field): mixed
    {
        $entries = data_get($record, 'reductionAdditions');

        if (! is_array($entries) || $entries === []) {
            return null;
        }

        if (count($entries) > 1 && $field === 'type') {
            Log::warning('Weclapp article price carries several reductionAdditions; only the first is mirrored.', [
                'price_id' => data_get($record, 'id'),
                'count'    => count($entries),
            ]);
        }

        return data_get($entries[0], $field);
    }

    /**
     * One field of the articleImages entry Weclapp flags as the main image, or
     * null when the article has no flagged image.
     *
     * Selected by the flag rather than by position: Weclapp documents no ordering
     * for the collection, so `articleImages.0` would be right by luck rather than
     * by rule.
     */
    private static function mainImageField(object $record, string $field): mixed
    {
        $images = data_get($record, 'articleImages');

        if (! is_iterable($images)) {
            return null;
        }

        foreach ($images as $image) {
            if (data_get($image, 'mainImage') === true) {
                return data_get($image, $field);
            }
        }

        return null;
    }
}
