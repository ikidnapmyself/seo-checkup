<?php

namespace SEOCheckup\Tests\Checks;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Analyze;
use SEOCheckup\Tests\Support\AnalyzeFactory;
use SEOCheckup\Tests\Support\FakeDnsLookup;
use SEOCheckup\Tests\Support\FakeHttpClient;

final class LinkChecksTest extends TestCase
{
    /**
     * Review finding "Important 1": an apex -> www redirect moves the origin
     * relative hrefs resolve against. Resolving against the requested URL
     * invents links that do not exist.
     */
    public function testRelativeLinksResolveAgainstTheFinalUrlAfterARedirect(): void
    {
        $client = (new FakeHttpClient())
            ->route('http://example.com/', '', 301, ['Location' => 'https://www.example.com/home/'])
            ->route(
                'https://www.example.com/home/',
                '<html><body><a href="a">1</a></body></html>',
                200
            );

        $analyze = new Analyze('http://example.com/', $client, new FakeDnsLookup());

        self::assertSame(
            ['https://www.example.com/home/a'],
            array_values($analyze->inboundLinks()['data'])
        );
    }

    /**
     * Spec defect 5: the drifted resolver mangled document-relative paths.
     */
    public function testInboundLinksKeepsOnlySameHostLinksAndResolvesThem(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body>'
            . '<a href="/a">1</a>'
            . '<a href="deep/b">2</a>'
            . '<a href="https://other.test/c">3</a>'
            . '<a href="mailto:x@example.com">4</a>'
            . '</body></html>'
        );

        self::assertSame(
            ['https://example.com/a', 'https://example.com/deep/b'],
            array_values($analyze->inboundLinks()['data'])
        );
    }

    public function testUnderscoredLinksReportsOnlyInternalOnes(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body>'
            . '<a href="/with_underscore">1</a>'
            . '<a href="/clean">2</a>'
            . '<a href="https://other.test/also_under">3</a>'
            . '</body></html>'
        );

        self::assertSame(
            ['https://example.com/with_underscore'],
            array_values($analyze->underscoredLinks()['data'])
        );
    }

    public function testSocialMediaGroupsProfilesByNetwork(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body>'
            . '<a href="https://github.com/example">gh</a>'
            . '<a href="https://twitter.com/example">tw</a>'
            . '<a href="https://example.com/plain">no</a>'
            . '</body></html>'
        );

        $data = $analyze->socialMedia()['data'];

        self::assertSame(['https://github.com/example'], $data['GitHub']);
        self::assertSame(['https://twitter.com/example'], $data['Twitter']);
        self::assertArrayNotHasKey('Facebook', $data);
    }

    public function testBrokenLinksSortsByStatusClass(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/ok', 'fine', 200)
            ->route('https://example.com/gone', 'gone', 404)
            ->route('https://example.com/boom', 'boom', 500);

        $analyze = AnalyzeFactory::make(
            '<html><body>'
            . '<a href="/ok">1</a><a href="/gone">2</a><a href="/boom">3</a>'
            . '</body></html>',
            client: $client
        );

        $scan = $analyze->brokenLinks()['data']['scanned'];

        self::assertSame(['https://example.com/ok'], $scan['passed']['HTTP 200']);
        self::assertSame(['https://example.com/gone'], $scan['errors']['HTTP 404']);
        self::assertSame(['https://example.com/boom'], $scan['errors']['HTTP 500']);
    }

    /**
     * Spec defect 10: 999 is LinkedIn's bot-block status, not a broken link.
     */
    public function testBrokenLinksTreats999AsPassed(): void
    {
        $client = (new FakeHttpClient())->route('https://example.com/li', '', 999);

        $analyze = AnalyzeFactory::make(
            '<html><body><a href="/li">1</a></body></html>',
            client: $client
        );

        self::assertSame([], $analyze->brokenLinks()['data']['scanned']['errors']);
    }

    /**
     * Spec defect 10: the old "$i >= 25" break actually scanned 24.
     */
    public function testBrokenLinksScansUpToTheLimit(): void
    {
        $html   = '<html><body>';
        $client = new FakeHttpClient();

        for ($i = 0; $i < 30; ++$i) {
            $html .= sprintf('<a href="/p%d">%d</a>', $i, $i);
            $client->route(sprintf('https://example.com/p%d', $i), 'ok', 200);
        }

        $analyze = AnalyzeFactory::make($html . '</body></html>', client: $client);

        self::assertCount(
            Analyze::BROKEN_LINKS_LIMIT,
            $analyze->brokenLinks()['data']['scanned']['passed']['HTTP 200']
        );
        self::assertCount(30, $analyze->brokenLinks(30)['data']['scanned']['passed']['HTTP 200']);
    }

    /**
     * A negative limit reached array_slice() unchanged, where it means "all
     * but the last N" rather than "none".
     *
     * @return list<array{int}>
     */
    public static function nonPositiveLimits(): array
    {
        return [[0], [-1], [-30]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonPositiveLimits')]
    public function testBrokenLinksScansNothingForANonPositiveLimit(int $limit): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/a', 'ok', 200)
            ->route('https://example.com/b', 'ok', 200);

        $analyze = AnalyzeFactory::make(
            '<html><body><a href="/a">1</a><a href="/b">2</a></body></html>',
            client: $client
        );

        $data = $analyze->brokenLinks($limit)['data'];

        self::assertSame(['errors' => [], 'passed' => []], $data['scanned']);
        self::assertSame(['https://example.com/a', 'https://example.com/b'], $data['links']);
    }
}
