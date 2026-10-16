<?php

declare(strict_types=1);

use Carbon\CarbonInterval;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
use Mindtwo\LaravelWeclappApi\WeclappClient as Client;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.page_size', 2);
});

it('resolves the client as a singleton from the container', function () {
    expect(app(Client::class))->toBeInstanceOf(Client::class)
        ->and(app(Client::class))->toBe(app(Client::class));
});

it('sends the authentication token header on requests', function () {
    Http::fake([
        '*' => Http::response(['result' => []], 200),
    ]);

    WeclappClient::get('party');

    Http::assertSent(fn ($request) => $request->hasHeader('AuthenticationToken', 'test-token')
        && $request->hasHeader('Accept', 'application/json'));
});

it('paginates a collection endpoint until a short page is returned', function () {
    Http::fakeSequence()
        ->push(['result' => [['id' => '1'], ['id' => '2']]], 200)
        ->push(['result' => [['id' => '3']]], 200);

    $result = WeclappClient::get('party');

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(3);

    Http::assertSentCount(2);
});

it('appends the dryRun flag to a post when requested', function () {
    Http::fake(['*' => Http::response(['id' => 'q-1'], 201)]);

    WeclappClient::post('quotation', ['foo' => 'bar'], dryRun: true);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'quotation?dryRun=true')
        && $request->method() === 'POST');
});

it('returns null when finding a missing record', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(WeclappClient::find('party', 'nope'))->toBeNull();
});

it('puts to an arbitrary record without a typed endpoint class', function () {
    Http::fake(['*' => Http::response(['id' => '7'], 200)]);

    $result = WeclappClient::put('warehouseStock', 7, ['quantity' => 3]);

    expect($result)->toBe(['id' => '7']);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/warehouseStock/id/7')
        && $request->method() === 'PUT'
        && $request['quantity'] === 3);
});

it('deletes an arbitrary record without a typed endpoint class', function () {
    Http::fake(['*' => Http::response('', 204)]);

    expect(WeclappClient::delete('ticket', 9))->toBeTrue();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/ticket/id/9')
        && $request->method() === 'DELETE');
});

it('throws when a generic write fails', function () {
    Http::fake(['*' => Http::response(['error' => 'nope'], 422)]);

    WeclappClient::put('ticket', 1, []);
})->throws(RequestException::class);

it('downloads a binary document from a resource sub-path', function () {
    Http::fake(['*downloadMainArticleImage*' => Http::response('binary-jpeg-bytes', 200, [
        'Content-Type' => 'image/jpeg',
    ])]);

    $response = app(Client::class)->download('article/id/1964/downloadMainArticleImage');

    expect($response)->not->toBeNull()
        ->and($response->body())->toBe('binary-jpeg-bytes')
        ->and($response->header('Content-Type'))->toBe('image/jpeg');
});

it('returns null when downloading a document that does not exist', function () {
    Http::fake(['*downloadMainArticleImage*' => Http::response('', 404)]);

    expect(app(Client::class)->download('article/id/999/downloadMainArticleImage'))->toBeNull();
});

it('throws when a download is refused rather than reporting no document', function () {
    Http::fake(['*downloadMainArticleImage*' => Http::response('', 403)]);

    app(Client::class)->download('article/id/1964/downloadMainArticleImage');
})->throws(RequestException::class);

it('appends the dryRun flag to a put when requested', function () {
    Http::fake(['*' => Http::response(['id' => '1964'], 200)]);

    WeclappClient::put('article', 1964, ['name' => 'Widget'], dryRun: true);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'article/id/1964?dryRun=true')
        && $request->method() === 'PUT'
        && $request['name'] === 'Widget');
});

it('does not append the dryRun flag to a put by default', function () {
    Http::fake(['*' => Http::response([], 200)]);

    WeclappClient::put('article', 1964, []);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'dryRun')
        && str_ends_with($request->url(), '/article/id/1964'));
});

it('suppresses a dry-run put too, since it still leaves the environment', function () {
    config()->set('weclapp-api.writes_enabled', false);
    Http::fake();

    expect(WeclappClient::put('article', 1964, ['name' => 'Widget'], dryRun: true))->toBe([]);

    Http::assertNothingSent();
});

it('uploads bytes as a raw body with the filename in the query, not multipart', function () {
    Http::fake(['*uploadArticleImage*' => Http::response(['result' => ['id' => '2052097']], 200)]);

    $result = app(Client::class)->upload(
        'article/id/1964/uploadArticleImage',
        'binary-jpeg-bytes',
        'header.jpeg',
        'image/jpeg',
        ['mainImage' => 'true'],
    );

    expect($result)->toBe(['result' => ['id' => '2052097']]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->body() === 'binary-jpeg-bytes'
            && $request->hasHeader('Content-Type', 'image/jpeg')
            && str_contains($request->url(), 'name=header.jpeg')
            && str_contains($request->url(), 'mainImage=true');
    });
});

it('returns null when uploading against a record that does not exist', function () {
    Http::fake(['*uploadArticleImage*' => Http::response('', 404)]);

    expect(app(Client::class)->upload('article/id/999/uploadArticleImage', 'bytes', 'a.jpg'))->toBeNull();
});

it('throws when an upload is refused rather than reporting no record', function () {
    Http::fake(['*uploadArticleImage*' => Http::response('', 403)]);

    app(Client::class)->upload('article/id/1964/uploadArticleImage', 'bytes', 'a.jpg');
})->throws(RequestException::class);

it('suppresses an upload when writes are disabled', function () {
    config()->set('weclapp-api.writes_enabled', false);
    Http::fake();

    expect(app(Client::class)->upload('article/id/1964/uploadArticleImage', 'bytes', 'a.jpg'))->toBe([]);

    Http::assertNothingSent();
});

describe('retry policy', function () {
    beforeEach(function () {
        config()->set('weclapp-api.http.retry_times', 3);
        config()->set('weclapp-api.http.retry_sleep', 0);
    });

    it('does not retry a 404 when finding a record', function () {
        Http::fake(['*' => Http::response('', 404)]);

        expect(WeclappClient::find('party', 'nope'))->toBeNull();

        Http::assertSentCount(1);
    });

    it('does not retry a refused write', function () {
        Http::fake(['*' => Http::response(['detail' => 'invalid'], 422)]);

        expect(fn () => WeclappClient::post('quotation', ['foo' => 'bar']))->toThrow(RequestException::class);

        Http::assertSentCount(1);
    });

    // The fake does not record a request whose handler throws, so these two
    // count the attempts themselves instead of using assertSentCount().
    it('does not retry a post after a connection error, since Weclapp may have processed it', function () {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('timed out');
        });

        expect(fn () => WeclappClient::post('quotation', ['foo' => 'bar']))->toThrow(ConnectionException::class)
            ->and($calls)->toBe(1);
    });

    it('does not retry a post on a server error', function () {
        Http::fake(['*' => Http::response('', 502)]);

        expect(fn () => WeclappClient::post('quotation', ['foo' => 'bar']))->toThrow(RequestException::class);

        Http::assertSentCount(1);
    });

    it('retries a read on a server error', function () {
        Http::fakeSequence()
            ->push('', 503)
            ->push('', 503)
            ->push(['result' => [['id' => '1']]], 200);

        expect(WeclappClient::get('party'))->toHaveCount(1);

        Http::assertSentCount(3);
    });

    it('retries a read after a connection error', function () {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            if (++$calls < 2) {
                throw new ConnectionException('timed out');
            }

            return Http::response(['result' => [['id' => '1']]], 200);
        });

        expect(WeclappClient::get('party'))->toHaveCount(1)
            ->and($calls)->toBe(2);
    });

    it('retries a post that was rate limited, honouring Retry-After', function () {
        Sleep::fake();
        Http::fakeSequence()
            ->push('', 429, ['Retry-After' => '2'])
            ->push(['id' => 'q-1'], 201);

        expect(WeclappClient::post('quotation', ['foo' => 'bar']))->toBe(['id' => 'q-1']);

        Http::assertSentCount(2);
        Sleep::assertSlept(fn (CarbonInterval $duration) => $duration->totalMilliseconds === 2000.0);
    });

    it('retries an idempotent put on a server error', function () {
        Http::fakeSequence()
            ->push('', 500)
            ->push(['id' => '5'], 200);

        expect(WeclappClient::put('article', 5, ['name' => 'x']))->toBe(['id' => '5']);

        Http::assertSentCount(2);
    });
});
