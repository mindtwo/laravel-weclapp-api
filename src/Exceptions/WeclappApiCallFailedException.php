<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class WeclappApiCallFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $endpoint,
        public readonly string $method,
        public readonly int $statusCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(string $endpoint, string $method, Response $response): self
    {
        $body = $response->json();
        $detail = is_array($body) ? ($body['messages'][0]['message'] ?? $body['error'] ?? null) : null;

        return new self(
            $endpoint,
            $method,
            $response->status(),
            sprintf('Weclapp API call %s %s failed with status %d%s', $method, $endpoint, $response->status(), $detail ? ": {$detail}" : ''),
        );
    }
}
