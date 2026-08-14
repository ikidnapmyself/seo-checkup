<?php

namespace SEOCheckup\Tests\Checks;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Exception\RequestFailedException;
use SEOCheckup\Tests\Support\AnalyzeFactory;
use SEOCheckup\Tests\Support\FakeHttpClient;

final class NetworkChecksTest extends TestCase
{
    public function testFaviconPrefersTheRootIcon(): void
    {
        $client = (new FakeHttpClient())->route('https://example.com/favicon.ico', 'icon', 200);

        $analyze = AnalyzeFactory::make('<html><head></head></html>', client: $client);

        self::assertSame('https://example.com/favicon.ico', $analyze->favicon()['data']);
    }

    /**
     * Spec defect 1: the fallback path read $_GET['value'].
     */
    public function testFaviconFallsBackToTheDeclaredIconWithoutTouchingSuperglobals(): void
    {
        $_GET = [];

        $client = (new FakeHttpClient())->route('https://example.com/assets/icon.png', 'icon', 200);

        $analyze = AnalyzeFactory::make(
            '<html><head><link rel="icon" href="/assets/icon.png"></head></html>',
            client: $client
        );

        self::assertSame('https://example.com/assets/icon.png', $analyze->favicon()['data']);
    }

    public function testFaviconResolvesARelativeIconHref(): void
    {
        $client = (new FakeHttpClient())->route('https://example.com/img/icon.png', 'icon', 200);

        $analyze = AnalyzeFactory::make(
            '<html><head><link rel="shortcut icon" href="img/icon.png"></head></html>',
            client: $client
        );

        self::assertSame('https://example.com/img/icon.png', $analyze->favicon()['data']);
    }

    public function testFaviconIsEmptyWhenNothingResolves(): void
    {
        $analyze = AnalyzeFactory::make('<html><head></head></html>');

        self::assertSame('', $analyze->favicon()['data']);
    }

    /**
     * A broken first icon link must not stop the second, valid one from being tried.
     */
    public function testFaviconFallsBackToTheNextIconWhenTheFirstFails(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/good-icon.png', 'icon', 200);

        $analyze = AnalyzeFactory::make(
            '<html><head><link rel="icon" href="/bad-icon.png"><link rel="icon" href="/good-icon.png"></head></html>',
            client: $client
        );

        self::assertSame('https://example.com/good-icon.png', $analyze->favicon()['data']);
        self::assertContains('https://example.com/good-icon.png', $client->requested);
    }

    public function testFaviconIsEmptyWhenNoDeclaredIconResolves(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><head><link rel="icon" href="/bad1.png"><link rel="icon" href="/bad2.png"></head></html>'
        );

        self::assertSame('', $analyze->favicon()['data']);
    }

    public function testFaviconRespectsTheCandidateLimit(): void
    {
        $html   = '<html><head>';
        $client = new FakeHttpClient();

        for ($i = 0; $i < 20; ++$i) {
            $html .= sprintf('<link rel="icon" href="/icon%d.png">', $i);
        }

        $analyze = AnalyzeFactory::make($html . '</head></html>', client: $client);
        $analyze->favicon();

        $candidateRequests = array_filter(
            $client->requested,
            static fn (string $url): bool => str_contains($url, '.png')
        );

        self::assertLessThanOrEqual(\SEOCheckup\Analyze::FAVICON_CANDIDATE_LIMIT, count($candidateRequests));
    }

    public function testRobotsFileReturnsItsContents(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/robots.txt', "User-agent: *\nDisallow:", 200);

        $analyze = AnalyzeFactory::make('<html></html>', client: $client);

        self::assertStringContainsString('User-agent: *', $analyze->robotsFile()['data']);
    }

    public function testRobotsFileIsFalseWhenMissing(): void
    {
        self::assertFalse(AnalyzeFactory::make('<html></html>')->robotsFile()['data']);
    }

    /**
     * robots.txt was fetched twice: once for the status, once for the body.
     */
    public function testRobotsFileMakesExactlyOneRequest(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/robots.txt', "User-agent: *\nDisallow:", 200);

        $analyze = AnalyzeFactory::make('<html></html>', client: $client);
        $analyze->robotsFile();

        $robotsRequests = array_filter(
            $client->requested,
            static fn (string $url): bool => str_contains($url, 'robots.txt')
        );

        self::assertCount(1, $robotsRequests);
    }

    public function testRobotsFileReturnsFalseOnTransportFailure(): void
    {
        $client  = new FakeHttpClient();
        $analyze = AnalyzeFactory::make('<html></html>', client: $client);

        $client->failWith = new RequestFailedException('boom');

        self::assertFalse($analyze->robotsFile()['data']);
    }

    public function testGoogleAnalyticsFindsAnInlineTrackingId(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><head><script>ga("create", "UA-12345-1");</script></head></html>'
        );

        self::assertSame('UA-12345-1', $analyze->googleAnalytics()['data']);
    }

    public function testGoogleAnalyticsFindsAnIdInAnExternalScript(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/assets/app.js', 'var id = "UA-99999-2";', 200);

        $analyze = AnalyzeFactory::make(
            '<html><head><script src="/assets/app.js"></script></head></html>',
            client: $client
        );

        self::assertSame('UA-99999-2', $analyze->googleAnalytics()['data']);
    }

    /**
     * Spec defect 2: $ua_id[0][0] on no match was an undefined-index error.
     */
    public function testGoogleAnalyticsIsEmptyWhenNoIdIsPresent(): void
    {
        $analyze = AnalyzeFactory::make('<html><head><script>noop();</script></head></html>');

        self::assertSame('', $analyze->googleAnalytics()['data']);
    }

    public function testGoogleAnalyticsStopsAfterTheExternalScriptLimit(): void
    {
        $html   = '<html><head>';
        $client = new FakeHttpClient();

        for ($i = 0; $i < 20; ++$i) {
            $html .= sprintf('<script src="/s%d.js"></script>', $i);
            $client->route(sprintf('https://example.com/s%d.js', $i), 'noop();', 200);
        }

        $analyze = AnalyzeFactory::make($html . '</head></html>', client: $client);
        $analyze->googleAnalytics();

        $scriptRequests = array_filter(
            $client->requested,
            static fn (string $url): bool => str_contains($url, '.js')
        );

        self::assertLessThanOrEqual(\SEOCheckup\Analyze::EXTERNAL_SCRIPT_LIMIT, count($scriptRequests));
    }
}
