<?php

declare(strict_types=1);

// A `KEY=` line in .env reaches env() as '' rather than null, so the config
// must treat a blank value like an unset one. A blank WRITES_ENABLED must
// never switch production writes off.

/**
 * @return array<string, mixed>
 */
function loadConfigWithEnv(array $overrides): array
{
    $names = [
        'APP_ENV',
        'MINDTWO_WECLAPP_PAGE_SIZE',
        'MINDTWO_WECLAPP_TIMEZONE',
        'MINDTWO_WECLAPP_WRITES_ENABLED',
        'MINDTWO_WECLAPP_HTTP_TIMEOUT',
        'MINDTWO_WECLAPP_HTTP_CONNECT_TIMEOUT',
        'MINDTWO_WECLAPP_HTTP_RETRY_TIMES',
        'MINDTWO_WECLAPP_HTTP_RETRY_SLEEP',
        'MINDTWO_WECLAPP_QUEUE_CONNECTION',
        'MINDTWO_WECLAPP_RATE_LIMIT_PER_MINUTE',
        'MINDTWO_WECLAPP_LOG_EVENTS',
        'MINDTWO_WECLAPP_LOG_LEVEL',
        'MINDTWO_WECLAPP_LOG_CHANNEL',
        'MINDTWO_WECLAPP_LOG_INCLUDE_PAYLOAD',
    ];

    $previous = [];

    foreach ($names as $name) {
        $previous[$name] = $_ENV[$name] ?? null;
        $value = $overrides[$name] ?? '';
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    try {
        return require __DIR__.'/../../config/weclapp-api.php';
    } finally {
        foreach ($previous as $name => $value) {
            if ($value === null) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
            } else {
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                putenv("{$name}={$value}");
            }
        }
    }
}

it('falls back to the defaults when every optional variable is blank', function () {
    $config = loadConfigWithEnv(['APP_ENV' => 'production']);

    expect($config['page_size'])->toBe(1000)
        ->and($config['timezone'])->toBe('UTC')
        ->and($config['writes_enabled'])->toBeTrue()
        ->and($config['http'])->toBe(['timeout' => 60, 'connect_timeout' => 10, 'retry_times' => 3, 'retry_sleep' => 500])
        ->and($config['queue_connection'])->toBeNull()
        ->and($config['rate_limit_per_minute'])->toBe(100)
        ->and($config['logging'])->toBe(['enabled' => false, 'level' => 'info', 'channel' => null, 'include_payload' => false]);
});

it('defaults writes to off only on local and testing', function (string $env, bool $expected) {
    $config = loadConfigWithEnv(['APP_ENV' => $env]);

    expect($config['writes_enabled'])->toBe($expected);
})->with([
    ['local', false],
    ['testing', false],
    ['staging', true],
    ['production', true],
]);

it('reads an explicit writes flag in every common spelling', function (string $value, bool $expected) {
    $config = loadConfigWithEnv(['APP_ENV' => 'production', 'MINDTWO_WECLAPP_WRITES_ENABLED' => $value]);

    expect($config['writes_enabled'])->toBe($expected);
})->with([
    ['false', false],
    ['0', false],
    ['off', false],
    ['no', false],
    ['true', true],
    ['1', true],
]);

it('honours explicit optional values', function () {
    $config = loadConfigWithEnv([
        'APP_ENV'                      => 'production',
        'MINDTWO_WECLAPP_PAGE_SIZE'    => '250',
        'MINDTWO_WECLAPP_HTTP_TIMEOUT' => '15',
        'MINDTWO_WECLAPP_LOG_EVENTS'   => 'true',
        'MINDTWO_WECLAPP_LOG_LEVEL'    => 'debug',
        'MINDTWO_WECLAPP_LOG_CHANNEL'  => 'weclapp',
    ]);

    expect($config['page_size'])->toBe(250)
        ->and($config['http']['timeout'])->toBe(15)
        ->and($config['logging']['enabled'])->toBeTrue()
        ->and($config['logging']['level'])->toBe('debug')
        ->and($config['logging']['channel'])->toBe('weclapp');
});
