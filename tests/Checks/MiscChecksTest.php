<?php

namespace SEOCheckup\Tests\Checks;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Analyze;
use SEOCheckup\Tests\Support\AnalyzeFactory;
use SEOCheckup\Tests\Support\FakeDnsLookup;
use SEOCheckup\Tests\Support\FakeHttpClient;

final class MiscChecksTest extends TestCase
{
    public function testHttpsIsTrueForAnHttpsUrl(): void
    {
        self::assertTrue(AnalyzeFactory::make('<html></html>')->https()['data']);
    }

    public function testHttpsIsFalseForAnHttpUrl(): void
    {
        $client  = (new FakeHttpClient())->route('http://example.com/', '<html></html>', 200);
        $analyze = new Analyze('http://example.com/', $client, new FakeDnsLookup());

        self::assertFalse($analyze->https()['data']);
    }

    /**
     * Review finding "Important 1": http -> https is the most common redirect
     * on the web, and the verdict has to describe the page that was served.
     */
    public function testHttpsReflectsTheFinalUrlAfterARedirect(): void
    {
        $analyze = self::redirected();

        self::assertTrue($analyze->https()['data']);
    }

    /**
     * The envelope's "url" stays the caller's own string: it answers "what did
     * I ask for", while the checks reason about where the request landed.
     */
    public function testEnvelopeStillReportsTheRequestedUrlAfterARedirect(): void
    {
        self::assertSame('http://example.com/', self::redirected()->https()['url']);
    }

    private static function redirected(): Analyze
    {
        $client = (new FakeHttpClient())
            ->route('http://example.com/', '', 301, ['Location' => 'https://www.example.com/home'])
            ->route('https://www.example.com/home', '<html></html>', 200);

        return new Analyze('http://example.com/', $client, new FakeDnsLookup());
    }

    /**
     * Spec defect 9: the length is measured on the host minus its last label.
     * Multi-label suffixes such as .co.uk are knowingly wrong until the
     * Public Suffix List lands — that is a deferred item, asserted here so
     * the behaviour is pinned rather than accidental.
     *
     * This test is not a regression test for defect 9's crash: it passes
     * against the pre-fix revision too. The hostless-URL crash it was written
     * for became structurally unreachable once Url::fromString() started
     * rejecting URLs without a host, so no input reaching domainLength() can
     * still trigger it. UrlTest::testRejectsUrlWithoutHost is where that
     * crash is actually covered; what this test pins is the measurement rule.
     *
     * @return list<array{string, int}>
     */
    public static function hosts(): array
    {
        return [
            ['https://example.com/', 7],      // "example"
            ['https://www.example.com/', 11], // "www.example"
            ['https://example.co.uk/', 10],   // "example.co" - documented limitation
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hosts')]
    public function testDomainLength(string $url, int $expected): void
    {
        $client  = (new FakeHttpClient())->route($url, '<html></html>', 200);
        $analyze = new Analyze($url, $client, new FakeDnsLookup());

        self::assertSame($expected, $analyze->domainLength()['data']);
    }

    public function testPageSpeedIsTheFetchDuration(): void
    {
        $speed = AnalyzeFactory::make('<html></html>')->pageSpeed()['data'];

        self::assertMatchesRegularExpression('/^\d+\.\d{4}$/', $speed);
    }

    public function testSpfRecordReturnsMatchingTxtRecords(): void
    {
        $dns = new FakeDnsLookup([
            ['type' => 'TXT', 'txt' => 'v=spf1 include:_spf.example.com ~all'],
            ['type' => 'TXT', 'txt' => 'google-site-verification=abc'],
        ]);

        $analyze = AnalyzeFactory::make('<html></html>', dns: $dns);

        self::assertSame(['v=spf1 include:_spf.example.com ~all'], $analyze->spfRecord()['data']);
    }

    /**
     * SPF is a property of the mail domain — normally the apex — not of
     * wherever the web server redirected to. On the most common setup on
     * the web, apex -> www, querying the served host reports "no SPF" for a
     * domain that publishes one. Master queried the requested host.
     */
    public function testSpfRecordQueriesTheRequestedHostNotTheRedirectTarget(): void
    {
        $client = (new FakeHttpClient())
            ->route('https://example.com/', '', 301, ['Location' => 'https://www.example.com/'])
            ->route('https://www.example.com/', '<html></html>', 200);
        $dns = new FakeDnsLookup([['type' => 'TXT', 'txt' => 'v=spf1 -all']]);

        $analyze = new Analyze('https://example.com/', $client, $dns);

        self::assertSame(['v=spf1 -all'], $analyze->spfRecord()['data']);
        self::assertSame(['example.com'], $dns->queried);
    }

    public function testSpfRecordIsEmptyWithoutRecords(): void
    {
        self::assertSame([], AnalyzeFactory::make('<html></html>')->spfRecord()['data']);
    }

    public function testSpfRecordSurvivesMalformedRecords(): void
    {
        $dns = new FakeDnsLookup([
            ['type' => 'TXT'],
            ['txt' => 'v=spf1 -all'],
            ['type' => 'MX', 'target' => 'mail.example.com'],
        ]);

        self::assertSame([], AnalyzeFactory::make('<html></html>', dns: $dns)->spfRecord()['data']);
    }
}
