<?php

declare(strict_types=1);

// The config and .env.example once named the same settings differently, so a
// token placed under the documented name resolved to '' and every request went
// out unauthenticated. These tests keep the two files in agreement.

function configEnvNames(): array
{
    $source = (string) file_get_contents(__DIR__.'/../../config/weclapp-api.php');

    // Tolerates whitespace after the paren, so a reformat cannot drop a variable
    // from the two checks that depend on this list.
    preg_match_all("/env\(\s*'([A-Z0-9_]+)'/", $source, $matches);

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
