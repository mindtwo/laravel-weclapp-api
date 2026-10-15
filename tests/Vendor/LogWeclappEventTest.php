<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Listeners\LogWeclappEvent;

it('logs the completed call on the configured channel and level', function () {
    config()->set('weclapp-api.logging.channel', 'stack');
    config()->set('weclapp-api.logging.level', 'notice');

    Log::shouldReceive('channel')->once()->with('stack')->andReturnSelf();
    Log::shouldReceive('log')->once()->with(
        'notice',
        'Weclapp API call completed',
        Mockery::on(fn (array $context) => $context['endpoint'] === 'quotation'
            && $context['method'] === 'POST'
            && $context['successful'] === true
            && ! array_key_exists('response', $context)),
    );

    (new LogWeclappEvent)->handle(new WeclappApiCallCompleted('quotation', 'POST', ['id' => 'q-1'], 201, true));
});

it('includes the payload when configured to', function () {
    config()->set('weclapp-api.logging.include_payload', true);

    Log::shouldReceive('channel')->once()->andReturnSelf();
    Log::shouldReceive('log')->once()->with(
        'info',
        'Weclapp API call completed',
        Mockery::on(fn (array $context) => ($context['response'] ?? null) === ['id' => 'q-1']),
    );

    (new LogWeclappEvent)->handle(new WeclappApiCallCompleted('quotation', 'POST', ['id' => 'q-1'], 201, true));
});
