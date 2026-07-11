# Laravel Weclapp API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mindtwo/laravel-weclapp-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-weclapp-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-weclapp-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mindtwo/laravel-weclapp-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-weclapp-api/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mindtwo/laravel-weclapp-api/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mindtwo/laravel-weclapp-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-weclapp-api)

This Laravel package provides a convenient and efficient way to integrate your
Laravel application with Weclapp, a cloud-based ERP and CRM platform. Designed
for ease of use, it offers a seamless connection, enabling your Laravel
application to interact
with [Weclapp's REST API](https://www.weclapp.com/api/v2.html) to read and
manage your business data. Whether you're looking to automate record updates,
synchronize entities, or react to changes via webhooks, this package streamlines
the process, making it easier to keep your Weclapp data in sync directly from
your Laravel application.

**Please note:** that this package currently supports a limited set of Weclapp
endpoints, specifically those related to Tasks, Attachments, Custom Fields, and
Webhooks.
We are actively working to expand our coverage of Weclapp's API to include more
endpoints and functionalities.
If you require integration with aspects of Weclapp not yet covered by our
package, we appreciate your patience and welcome contributions or suggestions to
enhance our offering.

## Installation

You can install the package via composer:

```bash
composer require mindtwo/laravel-weclapp-api
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="weclapp-api-config"
```

This is the contents of the published [config file](config/weclapp-api.php).

You must publish and run the package migrations:

```bash
php artisan vendor:publish --tag="weclapp-api-migrations"
php artisan migrate
```

The migrations will create the necessary database tables for managing Weclapp webhooks, including:
- `weclapp_webhooks` - Stores webhook registrations and health status
- `weclapp_webhook_deliveries` - Tracks webhook delivery history

## ENV Configuration

To ensure the proper functioning of this Laravel package with Weclapp, you must
provide your Weclapp API token by setting the `MINDTWO_WECLAPP_API_KEY` variable
in your application's environment configuration. Here's how to find your Weclapp
API token:

1. Log in to your Weclapp account and open your personal settings via the user
   menu in the top-right corner.
2. Navigate to the "API" (personal API token) section of your settings.
3. Generate a new personal API token, or copy the existing one if you've already
   created it.
4. Copy the token and add it to your Laravel `.env` file as follows:
   ```
   MINDTWO_WECLAPP_API_KEY=your_weclapp_api_token_here
   ```

Make sure to replace `your_weclapp_api_token_here` with the actual token you
obtained from Weclapp. This step is crucial for authenticating your Laravel
application's requests to Weclapp's API.

The following additional environment variables are supported (see the
[config file](config/weclapp-api.php) for details):

```
MINDTWO_WECLAPP_QUEUE_API_CALLS=
MINDTWO_WECLAPP_QUEUE_CONNECTION=
MINDTWO_WECLAPP_RATE_LIMIT_PER_MINUTE=
```

## Usage

This package provides a clean and intuitive Facade interface for interacting with Weclapp's API. All endpoints can be accessed through the `Weclapp` facade, making your code more readable and maintainable.

### Using the Facade

First, import the Weclapp facade at the top of your PHP file:

```php
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient as Weclapp;
```

Now you can access all Weclapp endpoints through the facade:

```php
// Tasks
Weclapp::tasks()->index($filters);
Weclapp::tasks()->show($taskId);
Weclapp::tasks()->create($taskDetails);
Weclapp::tasks()->update($taskId, $updates);
Weclapp::tasks()->delete($taskId);

// Custom Fields
Weclapp::customFields()->show($entityId);
Weclapp::customFields()->set($entityId, $fieldId, $value);

// Attachments
Weclapp::attachments()->create($entityId, $fileData);

// Webhooks
Weclapp::webhooks()->create($workspaceId, $webhookDetails);
```

### Alternative: Using the app() Helper

If you prefer not to use facades, you can still access endpoints using the `app()` helper:

```php
app(\Mindtwo\LaravelWeclappApi\Http\Endpoints\Task::class)->create($taskDetails);
```

However, we recommend using the Facade for cleaner and more readable code.

### Task Endpoint Usage

The `Task` class within the Laravel Weclapp API package provides a simple
interface for interacting with Weclapp's Task-related API endpoints. This class
allows you to list, view, create, update, and delete tasks within Weclapp,
directly from your Laravel application. Here's a quick overview of how to use
it:

- **List Tasks**: Retrieve tasks, optionally filtered via query parameters.
- **Show Task Details**: Get detailed information about a specific task using
  its task ID.
- **Create Task**: Create a new task by providing an array of task details.
- **Update Task**: Update an existing task by providing the task ID and the
  details to be updated.
- **Delete Task**: Delete a task using its task ID.

#### How to Use:

First, import the facade:

```php
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient as Weclapp;
```

1. **Create a Task**: To create a new task, you can use the `create` method.
   Pass an array of data that specifies the task details.

   ```php
   $taskDetails = [
       'subject' => 'New Task', // Mandatory
       'description' => 'Task description', // Optional
       // Add other task details as needed
   ];

   $task = Weclapp::tasks()->create($taskDetails);
   ```

2. **Get Tasks**: To retrieve tasks, use the `index` method with an optional
   array of query parameters to filter the results.

   ```php
   $tasks = Weclapp::tasks()->index([]);
   ```

3. **Show Task Details**: To get detailed information about a task, use the
   `show` method with the task ID.

   ```php
   $task = Weclapp::tasks()->show($taskId);
   ```

4. **Update a Task**: To update an existing task, use the `update` method with
   the task ID and an array of data with the details you wish to update.

   ```php
   $updatedDetails = [
       'name' => 'Updated Task Name',
       // Other task details you want to update
   ];

   $updatedTask = Weclapp::tasks()->update($taskId, $updatedDetails);
   ```

5. **Delete a Task**: To delete a task, use the `delete` method with the task
   ID.

   ```php
   $response = Weclapp::tasks()->delete($taskId);
   ```

These examples demonstrate the fundamental operations you can perform on tasks
within Weclapp through your Laravel application, providing a powerful way to
integrate task management functionalities into your workflow.

### Attachment Endpoint Usage

The `Attachment` class within this Laravel package facilitates the creation of
attachments in tasks on Weclapp. By utilizing the `create` method, users can
easily upload files and associate them with a specific task by its ID. This
functionality enhances the task management process, allowing for a more detailed
and resource-rich task structure.

To use this endpoint, first ensure that you have a task ID (`$taskId`) where you
want to attach a file, and prepare the data (`$data`) according to the Weclapp
API specifications for attachments. The `$data` should include the file
information structured in a way that's compatible with Weclapp's expectations
for attachment uploads.

Here's a basic example on how to use the `Attachment` endpoint:

```php
<?php

use Mindtwo\LaravelWeclappApi\Facades\WeclappClient as Weclapp;

// Assuming $taskId holds the ID of the task you want to attach a file to
// and $data contains the file and other required information as an array
$taskId = 'your_task_id_here';
$data = [
    [
        'name' => 'file',
        'contents' => fopen('/path/to/your/file', 'r'),
        'filename' => 'filename.ext',
    ],
    // Add other data fields as required by the Weclapp API for an attachment
];

// Creating an attachment to a task
Weclapp::attachments()->create($taskId, $data);
```

This simple interface abstracts away the complexity of dealing with multipart
file uploads and the Weclapp API, allowing you to focus on building your
application. Remember to replace `'your_task_id_here'` with the actual ID of the
task you're targeting and to adjust the `$data` array with the correct file path
and other necessary information as per your requirements.

### Custom Fields Endpoint Usage

The `CustomField` class within our Laravel Weclapp API package serves as a
dedicated endpoint for interacting with custom fields in Weclapp. Custom fields
are pivotal in tailoring Weclapp's entities to your business-specific
requirements, allowing for the addition of unique data fields to records.
Utilizing the `CustomField` class, you can effortlessly retrieve all custom
fields associated with a specific entity by providing the entity's ID.

Here's a quick guide on how to use the `CustomField` endpoint to fetch custom
fields:

```php
// Import the Weclapp facade at the top of your PHP file
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient as Weclapp;

// Assuming you have an entity ID, you can retrieve its custom fields like so:
$entityId = 'your_entity_id_here'; // Replace with your actual entity ID

// Fetch the custom fields for the specified entity
$customFields = Weclapp::customFields()->show($entityId);

// $customFields now contains the response from Weclapp API
```

Ensure you replace `'your_entity_id_here'` with the actual ID of the entity
whose custom fields you wish to retrieve. This simple and intuitive approach
allows you to integrate custom field data from Weclapp directly into your
Laravel application, enhancing data management and customization capabilities.

### ListCustomFieldsCommand

The ListCustomFieldsCommand class is a Laravel console command provided by the
Laravel Weclapp API package, enabling users to list all available custom fields
for a specified entity in Weclapp. By executing the command `php artisan
weclapp:list-custom-fields`, users can either provide an entity ID or mapping
key directly via the `--entity` option or they will be prompted to enter it.
This command is particularly useful for developers and administrators who need
to quickly view the custom field configurations within their Weclapp entities,
including details such as field ID, name, type, configuration options, creation
date, visibility, and whether the field is required. The command outputs this
information in a well-organized table format, making it easy to read and analyze
directly from the terminal.

## Webhook Security

Securing your Weclapp webhook endpoints is crucial to ensure that only legitimate webhook events from Weclapp are processed by your application. This package provides built-in signature verification using HMAC-SHA256.

### How Webhook Signature Verification Works

When Weclapp sends a webhook event to your application, it includes an `X-Signature` header containing an HMAC-SHA256 hash of the webhook payload. This signature is generated using a secret key that is shared between Weclapp and your application.

The verification process:
1. Weclapp sends a webhook request with an `X-Signature` header
2. Your application retrieves the webhook secret from the database
3. The application computes the expected signature using HMAC-SHA256
4. The computed signature is compared with the provided signature using a timing-safe comparison
5. If the signatures match, the webhook is authentic and processing continues

### Registering the Middleware

To enable webhook signature verification, register the middleware in your application. You have two options:

**Option 1: Register globally in `bootstrap/app.php` (Laravel 11+)**

```php
use Mindtwo\LaravelWeclappApi\Http\Middleware\VerifyWeclappWebhookSignature;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'weclapp.webhook' => VerifyWeclappWebhookSignature::class,
        ]);
    })
    // ...
    ->create();
```

**Option 2: Register in `app/Http/Kernel.php` (Laravel 10)**

```php
protected $middlewareAliases = [
    // ... other middleware
    'weclapp.webhook' => \Mindtwo\LaravelWeclappApi\Http\Middleware\VerifyWeclappWebhookSignature::class,
];
```

### Applying the Middleware to Routes

Once registered, apply the middleware to your webhook route:

```php
// In your routes/web.php or routes/api.php
Route::post('/webhooks/weclapp', [WebhookController::class, 'handle'])
    ->middleware('weclapp.webhook');
```

Note: The package automatically registers webhook routes if `webhook.enabled` is set to `true` in the config file. The middleware is applied automatically to these routes.

### Webhook Secret Storage

When you create a webhook using Weclapp's API, Weclapp generates a unique secret for that webhook. This secret must be stored in your database to enable signature verification.

The package automatically captures and stores webhook secrets in the `weclapp_webhooks` table when creating webhooks via the `webhooks()->create()` or `webhooks()->createManaged()` methods:

```php
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient as Weclapp;

// Create a webhook - secret is automatically captured
$response = Weclapp::webhooks()->create($workspaceId, [
    'endpoint' => 'https://your-app.com/webhooks/weclapp',
    'events' => ['taskCreated', 'taskUpdated'],
]);

// The webhook secret is stored in the database
$webhook = $response['webhook'];
// Secret is available at: $webhook['secret']
```

### Security Best Practices

1. **Always use HTTPS**: Configure your webhook endpoint to use HTTPS to prevent man-in-the-middle attacks
2. **Keep secrets secure**: Never commit webhook secrets to version control or expose them in logs
3. **Validate webhook IDs**: The middleware checks that the webhook ID exists in your database before processing
4. **Use timing-safe comparison**: The package uses `hash_equals()` for constant-time signature comparison to prevent timing attacks
5. **Monitor failed attempts**: Failed signature verifications are logged with IP addresses for security monitoring
6. **Disable inactive webhooks**: Use the health monitoring system to automatically disable failing webhooks

### Troubleshooting Signature Verification

If webhook signature verification is failing:

1. **Check the webhook exists**: Ensure the webhook is registered in your `weclapp_webhooks` table
2. **Verify the secret is stored**: Check that the `secret` column is not null for the webhook
3. **Check the X-Signature header**: Ensure Weclapp is sending the `X-Signature` header
4. **Review logs**: Check your Laravel logs for signature verification warnings
5. **Test with Weclapp API**: Use Weclapp's webhook testing feature to verify your endpoint

Example log entry for a failed verification:

```
[2025-12-19 10:30:45] local.WARNING: Invalid Weclapp webhook signature
{
    "webhook_id": "wh_abc123",
    "ip": "192.168.1.1"
}
```

## Webhook Health Monitoring

This package includes automatic webhook health monitoring to ensure your Weclapp webhooks remain active and functional. The system periodically checks the health status of all registered webhooks and takes appropriate actions when issues are detected.

### Health Status

Weclapp webhooks can have three health statuses:

- **Active**: Webhook is healthy and receiving events
- **Failing**: Webhook returns unsuccessful HTTP codes or exceeds 7 seconds response time
- **Suspended**: Webhook has reached 100 failed events and no longer receives events from Weclapp

### Automatic Health Checks

To enable automatic health monitoring, add the following to your application's `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Check Weclapp webhook health every hour
    $schedule->job(\Mindtwo\LaravelWeclappApi\Jobs\CheckWebhookHealth::class)
        ->hourly()
        ->name('weclapp-webhook-health-check')
        ->withoutOverlapping();
}
```

The health check job will:
- Query the Weclapp API to fetch current webhook health status
- Sync health data (status and fail count) to your local database
- Log warnings when webhook status changes
- Automatically disable webhooks that become failing or suspended

### Manual Webhook Recovery

If a webhook becomes failing or suspended, you can manually recover it using the recovery command:

**Recover a single webhook:**
```bash
php artisan weclapp:webhook-recover {webhook_id}
```

**Recover all failed/suspended webhooks:**
```bash
php artisan weclapp:webhook-recover --all
```

The recovery command will:
- Reactivate the webhook via Weclapp API by setting its status to active
- Reset the fail count to 0
- Enable the webhook in your local database
- Provide console feedback on success or failure

**Example output:**
```
Found 2 webhook(s) to recover.

Attempting to recover webhook: wh_abc123
  Status: failing
  Endpoint: https://your-app.com/webhooks/weclapp
  Fail count: 45
  ✓ Successfully recovered webhook wh_abc123

Attempting to recover webhook: wh_def456
  Status: suspended
  Endpoint: https://your-app.com/webhooks/weclapp
  Fail count: 100
  ✓ Successfully recovered webhook wh_def456

Recovery complete: 2 successful, 0 failed
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed
recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report
security vulnerabilities.

## Credits

- [mindtwo GmbH](https://github.com/mindtwo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
