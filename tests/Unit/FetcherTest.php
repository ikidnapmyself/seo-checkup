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

        self::assertSame('hello', (string) (new Fetcher($client))->get('https://example.com/')->getBody());
    }

    public function testDoesNotThrowOnHttpErrorStatus(): void
    {
        $client = (new FakeHttpClient())->route('https://example.com/', 'nope', 404);

        self::assertSame(404, (new Fetcher($client))->get('https://example.com/')->getStatusCode());
    }

    public function testFollowsRedirects(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/', '', 301, ['Location' => 'https://example.com/final'])
            ->route('https://example.com/final', 'arrived', 200);

        self::assertSame('arrived', (string) (new Fetcher($client))->get('https://example.com/')->getBody());
    }

    public function testResolvesRelativeRedirectTargets(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/a', '', 302, ['Location' => '/b'])
            ->route('https://example.com/b', 'arrived', 200);

        self::assertSame('arrived', (string) (new Fetcher($client))->get('https://example.com/a')->getBody());
    }

    public function testStopsAtTheRedirectCapAndReturnsTheLastResponse(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/loop', '', 302, ['Location' => 'https://example.com/loop']);

        $response = (new Fetcher($client))->get('https://example.com/loop');

        self::assertSame(302, $response->getStatusCode());
        self::assertCount(Fetcher::MAX_REDIRECTS + 1, $client->requested);
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

    public function testSendsAUserAgent(): void
    {
        $client = new class extends FakeHttpClient {
            public ?Request $lastRequest = null;

            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                \PHPUnit\Framework\Assert::assertNotSame('', $request->getHeaderLine('User-Agent'));

                return parent::sendRequest($request);
            }
        };

        (new Fetcher($client))->get('https://example.com/');
    }
}
