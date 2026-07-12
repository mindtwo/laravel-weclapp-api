<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Weclapp Base URL
    |--------------------------------------------------------------------------
    |
    | The fully-qualified base URL of your Weclapp instance's REST API,
    | including the API version segment. A trailing slash is optional; the
    | client normalises it. Example: https://your-tenant.weclapp.com/webapp/api/v2/
    |
    */

    'base_url' => (string) env('WECLAPP_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Authentication Token
    |--------------------------------------------------------------------------
    |
    | The Weclapp API token, sent on every request in the AuthenticationToken
    | header. Create one per user under "My settings" in Weclapp.
    |
    */

    'token' => (string) env('WECLAPP_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Pagination Page Size
    |--------------------------------------------------------------------------
    |
    | Number of records requested per page when paginating a collection
    | endpoint. Weclapp caps this at 1000.
    |
    */

    'page_size' => (int) env('WECLAPP_PAGE_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | Hard limits and retry policy applied to every outbound request so a hung
    | connection can never block a worker or web request indefinitely. Timeouts
    | are in seconds, retry sleep in milliseconds.
    |
    */

    'http' => [
        'timeout'         => (int) env('WECLAPP_HTTP_TIMEOUT', 60),
        'connect_timeout' => (int) env('WECLAPP_HTTP_CONNECT_TIMEOUT', 10),
        'retry_times'     => (int) env('WECLAPP_HTTP_RETRY_TIMES', 3),
        'retry_sleep'     => (int) env('WECLAPP_HTTP_RETRY_SLEEP', 500),
    ],
];
