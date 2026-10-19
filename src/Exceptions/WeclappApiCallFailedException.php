<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class WeclappApiCallFailedException extends RuntimeException
{
    /**
     * @param list<array<string, mixed>> $validationErrors Weclapp's `validationErrors[]`, each with
     *                                                     `location`, `detail`, `errorCode`, `allowed`
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $method,
        public readonly int $statusCode,
        string $message,
        public readonly array $validationErrors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Weclapp answers with an RFC 7807 problem: `detail`/`title` describe it and
     * `validationErrors[]` says which field. The flat `error` and `messages[]`
     * are deprecated and only read when the others are absent.
     */
    public static function fromResponse(string $endpoint, string $method, Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $detail = $body['detail'] ?? $body['title'] ?? $body['messages'][0]['message'] ?? $body['error'] ?? null;
        $detail = is_string($detail) && $detail !== '' ? $detail : null;

        $validationErrors = array_values(array_filter(
            is_array($body['validationErrors'] ?? null) ? $body['validationErrors'] : [],
            'is_array',
        ));

        $message = sprintf('Weclapp API call %s %s failed with status %d', $method, $endpoint, $response->status());

        if ($detail !== null) {
            $message .= ": {$detail}";
        }

        if ($validationErrors !== []) {
            $message .= ' ('.self::summarise($validationErrors).')';
        }

        return new self($endpoint, $method, $response->status(), $message, $validationErrors);
    }

    /**
     * @param list<array<string, mixed>> $errors
     */
    private static function summarise(array $errors): string
    {
        $shown = array_map(
            fn (array $error): string => trim(
                (isset($error['location']) ? $error['location'].': ' : '')
                .($error['detail'] ?? $error['errorCode'] ?? 'invalid'),
            ),
            array_slice($errors, 0, 3),
        );

        $rest = count($errors) - count($shown);

        return implode('; ', $shown).($rest > 0 ? "; +{$rest} more" : '');
    }
}
