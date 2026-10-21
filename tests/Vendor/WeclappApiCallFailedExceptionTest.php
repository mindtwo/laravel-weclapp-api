<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelWeclappApi\Exceptions\WeclappApiCallFailedException;

function failedResponse(int $status, mixed $body): Response
{
    return new Response(new PsrResponse($status, [], is_string($body) ? $body : (string) json_encode($body)));
}

it('reads the RFC 7807 detail, preferring it over the deprecated fields', function () {
    $e = WeclappApiCallFailedException::fromResponse('article/id/9', 'PUT', failedResponse(403, [
        'title'    => 'Forbidden',
        'detail'   => 'missing permissions for articleType',
        'error'    => 'legacy text',
        'messages' => [['message' => 'legacy message']],
    ]));

    expect($e->getMessage())->toBe('Weclapp API call PUT article/id/9 failed with status 403: missing permissions for articleType')
        ->and($e->statusCode)->toBe(403)
        ->and($e->validationErrors)->toBe([]);
});

it('prefers the specific legacy message over the generic title when detail is missing', function () {
    $body = ['title' => 'validation failed', 'messages' => [['message' => 'customerId must not be null']], 'error' => 'customerId must not be null'];

    expect(WeclappApiCallFailedException::fromResponse('quotation', 'POST', failedResponse(400, $body))->getMessage())
        ->toEndWith(': customerId must not be null');
});

it('falls back to each field on its own', function () {
    expect(WeclappApiCallFailedException::fromResponse('quotation', 'POST', failedResponse(400, ['title' => 'Bad Request']))->getMessage())
        ->toEndWith(': Bad Request')
        ->and(WeclappApiCallFailedException::fromResponse('quotation', 'POST', failedResponse(400, ['messages' => [['message' => 'old style']]]))->getMessage())
        ->toEndWith(': old style')
        ->and(WeclappApiCallFailedException::fromResponse('quotation', 'POST', failedResponse(400, ['error' => 'older style']))->getMessage())
        ->toEndWith(': older style');
});

it('names the failing fields of a validation problem and keeps them on the exception', function () {
    $errors = [
        ['location' => 'customerId', 'detail' => 'must not be null', 'errorCode' => 'NOT_NULL'],
        ['location' => 'salesChannel', 'detail' => 'not allowed', 'allowed' => ['NET1', 'NET2']],
        ['location' => 'items[0].quantity', 'errorCode' => 'MIN'],
        ['location' => 'items[1].quantity', 'errorCode' => 'MIN'],
    ];

    $e = WeclappApiCallFailedException::fromResponse('quotation', 'POST', failedResponse(400, [
        'detail'           => 'validation failed',
        'validationErrors' => $errors,
    ]));

    expect($e->getMessage())->toBe(
        'Weclapp API call POST quotation failed with status 400: validation failed '
        .'(customerId: must not be null; salesChannel: not allowed; items[0].quantity: MIN; +1 more)',
    )->and($e->validationErrors)->toBe($errors);
});

it('copes with a body that is not JSON', function () {
    $e = WeclappApiCallFailedException::fromResponse('quotation', 'POST', failedResponse(502, '<html>Bad Gateway</html>'));

    expect($e->getMessage())->toBe('Weclapp API call POST quotation failed with status 502')
        ->and($e->validationErrors)->toBe([]);
});
