<?php

namespace SEOCheckup\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use SEOCheckup\Exception\RequestFailedException;
use SEOCheckup\Fetcher;
use SEOCheckup\Tests\Support\FakeHttpClient;

final class FetcherTest extends TestCase
{
    public function testReturnsTheResponse(): void
    {
        $client = (new FakeHttpClient())->route('https://example.com/', 'hello', 200);

        self::assertSame('hello', (string) (new Fetcher($client))->get('https://example.com/')->response->getBody());
    }

    public function testDoesNotThrowOnHttpErrorStatus(): void
    {
        $client = (new FakeHttpClient())->route('https://example.com/', 'nope', 404);

        self::assertSame(404, (new Fetcher($client))->get('https://example.com/')->response->getStatusCode());
    }

    public function testFollowsRedirects(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/', '', 301, ['Location' => 'https://example.com/final'])
            ->route('https://example.com/final', 'arrived', 200);

        self::assertSame('arrived', (string) (new Fetcher($client))->get('https://example.com/')->response->getBody());
    }

    public function testResolvesRelativeRedirectTargets(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/a', '', 302, ['Location' => '/b'])
            ->route('https://example.com/b', 'arrived', 200);

        self::assertSame('arrived', (string) (new Fetcher($client))->get('https://example.com/a')->response->getBody());
    }

    /**
     * A misconfigured server answering `Location: /robots new.txt` used to
     * escape get() as InvalidUrlException on the next hop, breaking the
     * "checks do not throw" contract. Guzzle on master encoded and followed.
     */
    public function testFollowsARelativeRedirectWithAnUnencodedSpace(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/robots.txt', '', 301, ['Location' => '/robots new.txt'])
            ->route('https://example.com/robots%20new.txt', 'arrived', 200);

        $fetched = (new Fetcher($client))->get('https://example.com/robots.txt');

        self::assertSame('arrived', (string) $fetched->response->getBody());
        self::assertSame('https://example.com/robots%20new.txt', (string) $fetched->url);
    }

    public function testSendsUrlEmbeddedCredentials(): void
    {
        $client = (new FakeHttpClient())->route('https://user:pw@example.com/', 'authed', 200);

        $fetched = (new Fetcher($client))->get('https://user:pw@example.com/');

        self::assertSame(['https://user:pw@example.com/'], $client->requested);
        self::assertSame('authed', (string) $fetched->response->getBody());
    }

    /**
     * A redirect that never terminates is a failed fetch, not a page: master's
     * Guzzle threw TooManyRedirectsException, and returning the 3xx stub as
     * "the page" let new Analyze() succeed with 29 checks run against it.
     */
    public function testThrowsWhenTheRedirectCapIsExhausted(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/loop', '', 302, ['Location' => 'https://example.com/loop']);

        try {
            (new Fetcher($client))->get('https://example.com/loop');
            self::fail('Expected RequestFailedException');
        } catch (RequestFailedException $e) {
            self::assertStringContainsString('redirects', $e->getMessage());
        }

        self::assertCount(Fetcher::MAX_REDIRECTS + 1, $client->requested);
    }

    public function testStatusReturnsZeroOnARedirectLoop(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/loop', '', 302, ['Location' => '/loop']);

        self::assertSame(0, (new Fetcher($client))->status('https://example.com/loop'));
    }

    public function testGetThrowsOnTransportFailure(): void
    {
        $client = new FakeHttpClient();
        $client->failWith = new class ('boom') extends \RuntimeException implements ClientExceptionInterface {
        };

        $this->expectException(RequestFailedException::class);
        (new Fetcher($client))->get('https://example.com/');
    }

    public function testStatusReturnsZeroOnTransportFailureInsteadOfThrowing(): void
    {
        $client = new FakeHttpClient();
        $client->failWith = new class ('boom') extends \RuntimeException implements ClientExceptionInterface {
        };

        self::assertSame(0, (new Fetcher($client))->status('https://example.com/'));
    }

    public function testBodyReturnsEmptyStringOnFailure(): void
    {
        $client = new FakeHttpClient();
        $client->failWith = new class ('boom') extends \RuntimeException implements ClientExceptionInterface {
        };

        self::assertSame('', (new Fetcher($client))->body('https://example.com/'));
    }

    /**
     * status() and body() are documented as total. Guzzle's StreamHandler
     * path -- used when curl is unavailable -- rethrows a bare
     * InvalidArgumentException from Response's constructor on an
     * out-of-range status code, and that does not implement
     * ClientExceptionInterface, so it escaped both methods.
     */
    public function testStatusReturnsZeroWhenTheClientThrowsABareInvalidArgument(): void
    {
        $client = new FakeHttpClient();
        $client->failWith = new \InvalidArgumentException('Status code must be an integer value between 1xx and 5xx.');

        self::assertSame(0, (new Fetcher($client))->status('https://example.com/'));
    }

    public function testBodyReturnsEmptyStringWhenTheClientThrowsABareInvalidArgument(): void
    {
        $client = new FakeHttpClient();
        $client->failWith = new \InvalidArgumentException('Status code must be an integer value between 1xx and 5xx.');

        self::assertSame('', (new Fetcher($client))->body('https://example.com/'));
    }

    public function testSendsAUserAgent(): void
    {
        $client = new class () extends FakeHttpClient {
            public ?Request $lastRequest = null;

            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                \PHPUnit\Framework\Assert::assertNotSame('', $request->getHeaderLine('User-Agent'));

                return parent::sendRequest($request);
            }
        };

        (new Fetcher($client))->get('https://example.com/');
    }

    public function testStatusReturnsZeroOnMalformedUrlInsteadOfThrowing(): void
    {
        $client = new FakeHttpClient();

        self::assertSame(0, (new Fetcher($client))->status('http://exa mple.com/'));
    }

    public function testBodyReturnsEmptyStringOnMalformedUrlInsteadOfThrowing(): void
    {
        $client = new FakeHttpClient();

        self::assertSame('', (new Fetcher($client))->body('http://host:abc/'));
    }

    public function testGetReturnsTheLastResponseWhenRedirectTargetFailsToParse(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/', '', 302, ['Location' => 'http://exa mple.com/']);

        $response = (new Fetcher($client))->get('https://example.com/')->response;

        self::assertSame(302, $response->getStatusCode());
    }

    /**
     * Review finding "Important 1": the URL the redirect chain ended on has to
     * survive the call, not just the body it returned.
     */
    public function testExposesTheFinalUrlAfterRedirects(): void
    {
        $client = (new FakeHttpClient())
            ->route('http://example.com/', '', 301, ['Location' => 'https://www.example.com/home'])
            ->route('https://www.example.com/home', 'arrived', 200);

        $fetched = (new Fetcher($client))->get('http://example.com/');

        self::assertSame('https://www.example.com/home', (string) $fetched->url);
        self::assertSame('arrived', (string) $fetched->response->getBody());
    }

    public function testFinalUrlIsTheRequestedUrlWhenNothingRedirects(): void
    {
        $client = (new FakeHttpClient())->route('https://example.com/a', 'hello', 200);

        self::assertSame('https://example.com/a', (string) (new Fetcher($client))->get('https://example.com/a')->url);
    }

    public function testAChainOfExactlyMaxRedirectsIsStillFollowed(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/1', '', 302, ['Location' => '/2'])
            ->route('https://example.com/2', '', 302, ['Location' => '/3'])
            ->route('https://example.com/3', '', 302, ['Location' => '/4'])
            ->route('https://example.com/4', '', 302, ['Location' => '/5'])
            ->route('https://example.com/5', '', 302, ['Location' => '/6'])
            ->route('https://example.com/6', 'arrived', 200);

        $fetched = (new Fetcher($client))->get('https://example.com/1');

        self::assertSame('https://example.com/6', (string) $fetched->url);
        self::assertSame('arrived', (string) $fetched->response->getBody());
    }

    /**
     * The last hop answering 3xx with a Location the resolver cannot use is
     * a terminal response, not an exhausted cap: it is returned as-is.
     */
    public function testAnUnresolvableLocationAtTheCapIsReturnedNotThrown(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/1', '', 302, ['Location' => '/2'])
            ->route('https://example.com/2', '', 302, ['Location' => '/3'])
            ->route('https://example.com/3', '', 302, ['Location' => '/4'])
            ->route('https://example.com/4', '', 302, ['Location' => '/5'])
            ->route('https://example.com/5', '', 302, ['Location' => '/6'])
            ->route('https://example.com/6', '', 302, ['Location' => 'mailto:x@example.com']);

        $fetched = (new Fetcher($client))->get('https://example.com/1');

        self::assertSame(302, $fetched->response->getStatusCode());
        self::assertSame('https://example.com/6', (string) $fetched->url);
    }
}
