<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class WeclappClient
{
    protected string $baseUrl;

    protected int $pageSize;

    public function __construct(string $baseUrl, protected string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/').'/';
        $this->pageSize = (int) config('weclapp-api.page_size', 1000);
    }

    /**
     * Fetch every record from a paginated collection endpoint, merging all pages.
     *
     * @param array<string, mixed> $params
     *
     * @throws RequestException
     *
     * @return Collection<int, object>
     */
    public function get(string $endpoint, array $params = []): Collection
    {
        $results = [];
        $page = 1;

        do {
            $response = $this->request()->get($this->url($endpoint), array_merge($params, [
                'page'     => $page,
                'pageSize' => $this->pageSize,
            ]));

            $response->throw();

            $batch = array_values(array_filter(
                (array) ($response->object()->result ?? []),
                'is_object',
            ));

            $results = [...$results, ...$batch];

            $page++;
        } while (count($batch) >= $this->pageSize);

        return collect($results);
    }

    /**
     * POST data to an endpoint. When $dryRun is true Weclapp validates the
     * payload without persisting it (server-side dry run).
     *
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     *
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data, bool $dryRun = false): array
    {
        $url = $this->url($endpoint);

        if ($dryRun) {
            $url .= '?dryRun=true';
        }

        $response = $this->request()->post($url, $data);

        $response->throw();

        return $response->json();
    }

    /**
     * PUT (replace) a single record by id.
     *
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     *
     * @return array<string, mixed>
     */
    public function put(string $endpoint, string|int $id, array $data): array
    {
        $response = $this->request()->put($this->url($endpoint).'/'.$id, $data);

        $response->throw();

        return $response->json();
    }

    /**
     * DELETE a single record by id.
     *
     * @throws RequestException
     */
    public function delete(string $endpoint, string|int $id): void
    {
        $this->request()->delete($this->url($endpoint).'/'.$id)->throw();
    }

    /**
     * Fetch a single record by id. Returns null on a 404.
     *
     * @throws RequestException
     */
    public function find(string $endpoint, string|int $id): ?object
    {
        $response = $this->request(throwOnRetry: false)->get($this->url($endpoint).'/'.$id);

        if ($response->notFound()) {
            return null;
        }

        $response->throw();

        return $response->object();
    }

    /**
     * The number of records matching the given filters.
     *
     * @param array<string, mixed> $params
     *
     * @throws RequestException
     */
    public function count(string $endpoint, array $params = []): int
    {
        $response = $this->request()->get($this->url($endpoint).'/count', $params);

        $response->throw();

        return (int) ($response->object()->result ?? 0);
    }

    protected function request(bool $throwOnRetry = true): PendingRequest
    {
        return Http::withHeaders([
            'AuthenticationToken' => $this->token,
            'Accept'              => 'application/json',
            'Content-Type'        => 'application/json',
        ])
            ->timeout((int) config('weclapp-api.http.timeout', 60))
            ->connectTimeout((int) config('weclapp-api.http.connect_timeout', 10))
            ->retry(
                (int) config('weclapp-api.http.retry_times', 3),
                (int) config('weclapp-api.http.retry_sleep', 500),
                throw: $throwOnRetry,
            );
    }

    protected function url(string $endpoint): string
    {
        return $this->baseUrl.ltrim($endpoint, '/');
    }
}
