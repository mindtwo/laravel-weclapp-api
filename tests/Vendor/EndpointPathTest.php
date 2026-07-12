<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
});

// Every typed accessor must hit its exact Weclapp v2 resource path. The paths
// are validated against the Weclapp Bruno collection (27) and cloudbase's
// production usage for customer/supplier/project (3). This guards against
// typos in the many trivial $path subclasses.
it('queries the correct Weclapp path for each endpoint accessor', function (string $accessor, string $path) {
    Http::fake(['*' => Http::response(['result' => []], 200)]);

    WeclappClient::{$accessor}()->query();

    Http::assertSent(fn ($request) => str_contains($request->url(), "/webapp/api/v2/{$path}?"));
})->with([
    'parties'            => ['parties', 'party'],
    'customers'          => ['customers', 'customer'],
    'suppliers'          => ['suppliers', 'supplier'],
    'projects'           => ['projects', 'project'],
    'articles'           => ['articles', 'article'],
    'articleCategories'  => ['articleCategories', 'articleCategory'],
    'quotations'         => ['quotations', 'quotation'],
    'salesOrders'        => ['salesOrders', 'salesOrder'],
    'users'              => ['users', 'user'],
    'salesInvoices'      => ['salesInvoices', 'salesInvoice'],
    'purchaseOrders'     => ['purchaseOrders', 'purchaseOrder'],
    'purchaseInvoices'   => ['purchaseInvoices', 'purchaseInvoice'],
    'blanketSalesOrders' => ['blanketSalesOrders', 'blanketSalesOrder'],
    'contracts'          => ['contracts', 'contract'],
    'opportunities'      => ['opportunities', 'opportunity'],
    'units'              => ['units', 'unit'],
    'taxes'              => ['taxes', 'tax'],
    'paymentMethods'     => ['paymentMethods', 'paymentMethod'],
    'termsOfPayment'     => ['termsOfPayment', 'termOfPayment'],
    'customerCategories' => ['customerCategories', 'customerCategory'],
    'leadSources'        => ['leadSources', 'leadSource'],
    'salesStages'        => ['salesStages', 'salesStage'],
    'currencies'         => ['currencies', 'currency'],
    'costCenters'        => ['costCenters', 'costCenter'],
    'ledgerAccounts'     => ['ledgerAccounts', 'ledgerAccount'],
    'warehouses'         => ['warehouses', 'warehouse'],
    'shipments'          => ['shipments', 'shipment'],
    'documents'          => ['documents', 'document'],
    'comments'           => ['comments', 'comment'],
    'webhooks'           => ['webhooks', 'webhook'],
]);
