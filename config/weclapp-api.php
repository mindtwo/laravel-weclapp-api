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

    'base_url' => (string) env('MINDTWO_WECLAPP_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Authentication Token
    |--------------------------------------------------------------------------
    |
    | The Weclapp API token, sent on every request in the AuthenticationToken
    | header. Create one per user under "My settings" in Weclapp.
    |
    */

    'token' => (string) env('MINDTWO_WECLAPP_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Pagination Page Size
    |--------------------------------------------------------------------------
    |
    | Number of records requested per page when paginating a collection
    | endpoint. Weclapp caps this at 1000.
    |
    */

    'page_size' => (int) (env('MINDTWO_WECLAPP_PAGE_SIZE') ?: 1000),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Weclapp returns timestamps as epoch milliseconds. The sync commands
    | convert them to datetimes in this timezone before persisting.
    |
    */

    'timezone' => env('MINDTWO_WECLAPP_TIMEZONE') ?: 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Write Requests Enabled
    |--------------------------------------------------------------------------
    |
    | When false, mutating requests (POST/PUT/DELETE) are suppressed and logged
    | instead of sent; reads still go through. Defaults to live everywhere
    | except the local and testing environments. Override with
    | MINDTWO_WECLAPP_WRITES_ENABLED. An unset or blank variable keeps the
    | default — a blank line in .env must never switch production writes off.
    |
    */

    'writes_enabled' => (static function (): bool {
        $flag = env('MINDTWO_WECLAPP_WRITES_ENABLED');

        if ($flag === null || $flag === '') {
            return ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true);
        }

        return filter_var($flag, FILTER_VALIDATE_BOOL);
    })(),

    /*
    |--------------------------------------------------------------------------
    | Visible Tag
    |--------------------------------------------------------------------------
    |
    | The Weclapp article tag that marks an article as belonging in your
    | application. The article mirror keeps it as the `visible` boolean. Leave
    | empty to treat every article as visible.
    |
    | `visible` is computed when a row is written. After changing this value
    | run a full `weclapp:sync articles`: `weclapp:update` only revisits the
    | articles Weclapp reports as modified, so the rest would keep the old flag.
    |
    */

    'visible_tag' => env('MINDTWO_WECLAPP_VISIBLE_TAG'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | Hard limits and retry policy applied to every outbound request so a hung
    | connection can never block a worker or web request indefinitely. Timeouts
    | are in seconds, retry sleep in milliseconds. retry_times counts every
    | attempt, the first one included, so 1 (or 0) means no retry. Both retry
    | values take an explicit 0; only a blank or missing one falls back.
    |
    */

    'http' => [
        'timeout'         => (int) (env('MINDTWO_WECLAPP_HTTP_TIMEOUT') ?: 60),
        'connect_timeout' => (int) (env('MINDTWO_WECLAPP_HTTP_CONNECT_TIMEOUT') ?: 10),
        'retry_times'     => is_numeric(env('MINDTWO_WECLAPP_HTTP_RETRY_TIMES')) ? (int) env('MINDTWO_WECLAPP_HTTP_RETRY_TIMES') : 3,
        'retry_sleep'     => is_numeric(env('MINDTWO_WECLAPP_HTTP_RETRY_SLEEP')) ? (int) env('MINDTWO_WECLAPP_HTTP_RETRY_SLEEP') : 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued API Calls
    |--------------------------------------------------------------------------
    |
    | Endpoint write methods return a LazyResponseProxy; calling ->getJob() on
    | it hands back an undispatched WeclappApiCallJob you can queue. These
    | settings control the connection that job runs on and the per-minute rate
    | limit applied to it (Weclapp throttles aggressively).
    |
    */

    'queue_connection'      => env('MINDTWO_WECLAPP_QUEUE_CONNECTION') ?: null,
    'rate_limit_per_minute' => (int) (env('MINDTWO_WECLAPP_RATE_LIMIT_PER_MINUTE') ?: 100),

    /*
    |--------------------------------------------------------------------------
    | Event Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, the package logs every WeclappApiCallCompleted event via the
    | configured channel — one per answered or suppressed write, from the typed
    | endpoints, the generic client and the queued job alike. A write that gets
    | no answer is only logged from the proxy or a job that gives up (see the
    | README). Reads emit nothing. Useful for auditing outbound writes; off by
    | default.
    |
    */

    'logging' => [
        'enabled'         => filter_var(env('MINDTWO_WECLAPP_LOG_EVENTS'), FILTER_VALIDATE_BOOL),
        'level'           => env('MINDTWO_WECLAPP_LOG_LEVEL') ?: 'info',
        'channel'         => env('MINDTWO_WECLAPP_LOG_CHANNEL') ?: null,
        'include_payload' => filter_var(env('MINDTWO_WECLAPP_LOG_INCLUDE_PAYLOAD'), FILTER_VALIDATE_BOOL),
    ],
];
