<?php

declare(strict_types=1);

// The config previously read WECLAPP_* while .env.example provided
// MINDTWO_WECLAPP_*, so a real token resolved to '' and every request went out
// unauthenticated. These tests keep the two files in agreement.

function configEnvNames(): array
{
    $source = (string) file_get_contents(__DIR__.'/../../config/weclapp-api.php');

    preg_match_all("/env\('([A-Z0-9_]+)'/", $source, $matches);

    return array_values(array_unique(array_diff($matches[1], ['APP_ENV'])));
}

it('reads every setting from a MINDTWO_WECLAPP_ prefixed variable', function () {
    $names = configEnvNames();

    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        expect($name)->toStartWith('MINDTWO_WECLAPP_');
    }
});

it('reads the credentials from the prepared variable names', function () {
    $source = (string) file_get_contents(__DIR__.'/../../config/weclapp-api.php');

    expect($source)->toContain("env('MINDTWO_WECLAPP_URL'")
        ->and($source)->toContain("env('MINDTWO_WECLAPP_API_KEY'");
});

it('documents every config variable in .env.example', function () {
    $example = (string) file_get_contents(__DIR__.'/../../.env.example');

    foreach (configEnvNames() as $name) {
        expect($example)->toContain($name.'=');
    }
});
