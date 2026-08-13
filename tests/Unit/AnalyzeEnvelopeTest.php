<?php

namespace SEOCheckup\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SEOCheckup\Analyze;
use SEOCheckup\Exception\InvalidUrlException;
use SEOCheckup\Exception\RequestFailedException;
use SEOCheckup\Tests\Support\AnalyzeFactory;
use SEOCheckup\Tests\Support\FakeHttpClient;

final class AnalyzeEnvelopeTest extends TestCase
{
    /**
     * Every check the library ships. The count is a spec guarantee: 29.
     *
     * @return list<array{string}>
     */
    public static function checks(): array
    {
        return array_map(static fn (string $m): array => [$m], [
            'brokenLinks', 'cache', 'canonicalTag', 'characterSet', 'codeContent',
            'deprecatedHtml', 'domainLength', 'favicon', 'frameset', 'googleAnalytics',
            'header1', 'header2', 'https', 'imageAlt', 'inboundLinks', 'inlineCss',
            'metaDescription', 'metaTitle', 'nofollowTag', 'noindexTag', 'objectCount',
            'pageSpeed', 'plaintextEmail', 'pageCompression', 'robotsFile',
            'serverSignature', 'socialMedia', 'spfRecord', 'underscoredLinks',
        ]);
    }

    public function testShipsExactlyTwentyNineChecks(): void
    {
        $public = array_filter(
            get_class_methods(Analyze::class),
            static fn (string $m): bool => $m !== '__construct',
        );

        self::assertCount(29, $public);
        self::assertSame([], array_diff($public, array_column(self::checks(), 0)));
    }

    #[DataProvider('checks')]
    public function testCheckReturnsAWellFormedEnvelope(string $check): void
    {
        $analyze = AnalyzeFactory::make(AnalyzeFactory::fixture('complete.html'));

        $result = $analyze->{$check}();

        self::assertSame(
            ['url', 'status', 'headers', 'service', 'time', 'data'],
            array_keys($result)
        );
        self::assertSame(AnalyzeFactory::URL, $result['url']);
        self::assertSame(200, $result['status']);
        self::assertIsInt($result['time']);
    }

    public function testServiceLabelIsHumanReadable(): void
    {
        $analyze = AnalyzeFactory::make(AnalyzeFactory::fixture('complete.html'));

        self::assertSame('Broken Links', $analyze->brokenLinks()['service']);
        self::assertSame('Https', $analyze->https()['service']);
        self::assertSame('Spf Record', $analyze->spfRecord()['service']);
    }

    public function testRejectsAMalformedUrl(): void
    {
        $this->expectException(InvalidUrlException::class);
        new Analyze('not a url');
    }

    public function testWrapsTransportFailure(): void
    {
        $client = new FakeHttpClient();
        $client->failWith = new class ('down') extends \RuntimeException implements \Psr\Http\Client\ClientExceptionInterface {
        };

        $this->expectException(RequestFailedException::class);
        new Analyze('https://example.com/', $client);
    }

    public function testDoesNotLookUpDnsUnlessSpfIsRequested(): void
    {
        $dns = new class () implements \SEOCheckup\DnsLookup {
            public int $calls = 0;

            public function txtRecords(string $host): array
            {
                ++$this->calls;

                return [];
            }
        };

        $analyze = AnalyzeFactory::make(AnalyzeFactory::fixture('complete.html'), dns: $dns);
        $analyze->metaTitle();

        self::assertSame(0, $dns->calls);

        $analyze->spfRecord();

        self::assertSame(1, $dns->calls);
    }
}
