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
     * Weclapp answers with an RFC 7807 problem: `detail` describes this case and
     * `validationErrors[]` says which field. `detail` is often missing, above all
     * on validation errors, so the deprecated `messages[]` and `error`, which are
     * still sent, come next; `title` only names the problem type ("validation
     * failed") and is always present, so it comes last.
     */
    public static function fromResponse(string $endpoint, string $method, Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $detail = $body['detail'] ?? $body['messages'][0]['message'] ?? $body['error'] ?? $body['title'] ?? null;
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
