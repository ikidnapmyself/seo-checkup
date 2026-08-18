<?php

namespace SEOCheckup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Url;
use SEOCheckup\UrlResolver;

final class UrlResolverTest extends TestCase
{
    private Url $base;

    protected function setUp(): void
    {
        $this->base = Url::fromString('https://example.com/blog/post.html?x=1');
    }

    public function testKeepsAbsoluteUrl(): void
    {
        self::assertSame(
            'https://other.test/a',
            UrlResolver::resolve($this->base, 'https://other.test/a')
        );
    }

    public function testResolvesProtocolRelative(): void
    {
        self::assertSame(
            'https://cdn.test/a.js',
            UrlResolver::resolve($this->base, '//cdn.test/a.js')
        );
    }

    public function testResolvesRootRelative(): void
    {
        self::assertSame(
            'https://example.com/about',
            UrlResolver::resolve($this->base, '/about')
        );
    }

    public function testResolvesDocumentRelativeAgainstTheDirectory(): void
    {
        self::assertSame(
            'https://example.com/blog/next.html',
            UrlResolver::resolve($this->base, 'next.html')
        );
    }

    public function testResolvesDotSegments(): void
    {
        self::assertSame(
            'https://example.com/about.html',
            UrlResolver::resolve($this->base, '../about.html')
        );
    }

    public function testKeepsQueryAndDropsFragment(): void
    {
        self::assertSame(
            'https://example.com/blog/a.html?p=2',
            UrlResolver::resolve($this->base, 'a.html?p=2#section')
        );
    }

    public function testPreservesTrailingSlashWhenDotSegmentConsumesLastSegment(): void
    {
        self::assertSame(
            'https://example.com/blog/',
            UrlResolver::resolve($this->base, 'foo/..')
        );
    }

    public function testPreservesTrailingSlashForCurrentDirectory(): void
    {
        self::assertSame(
            'https://example.com/blog/',
            UrlResolver::resolve($this->base, '.')
        );
    }

    public function testPreservesTrailingSlashWhenDoubleDotConsumesLastSegment(): void
    {
        self::assertSame(
            'https://example.com/blog/a/',
            UrlResolver::resolve($this->base, 'a/b/..')
        );
    }

    public function testPreservesTrailingSlashInLiteralHref(): void
    {
        self::assertSame(
            'https://example.com/blog/foo/',
            UrlResolver::resolve($this->base, 'foo/')
        );
    }

    public function testPreservesTrailingSlashInNestedPath(): void
    {
        self::assertSame(
            'https://example.com/blog/sub/dir/',
            UrlResolver::resolve($this->base, 'sub/dir/')
        );
    }

    public function testPreservesDoubleSplash(): void
    {
        self::assertSame(
            'https://example.com/blog/foo//bar',
            UrlResolver::resolve($this->base, 'foo//bar')
        );
    }

    /**
     * RFC 3986 section 5.3: a reference with an empty path and a query keeps
     * the base path unchanged. `<a href="?page=2">` is standard pagination
     * markup, and appending it to the base's directory invents a URL.
     */
    public function testQueryOnlyReferenceKeepsTheBasePath(): void
    {
        self::assertSame(
            'https://example.com/blog/post.html?page=2',
            UrlResolver::resolve($this->base, '?page=2')
        );
    }

    public function testQueryOnlyReferenceOnARootBaseKeepsTheRootPath(): void
    {
        self::assertSame(
            'https://example.com/?page=2',
            UrlResolver::resolve(Url::fromString('https://example.com/'), '?page=2')
        );
    }

    public function testQueryOnlyReferenceKeepsADirectoryBasePath(): void
    {
        self::assertSame(
            'https://example.com/blog/?page=2',
            UrlResolver::resolve(Url::fromString('https://example.com/blog/'), '?page=2')
        );
    }

    public function testQueryOnlyReferenceDropsTheFragment(): void
    {
        self::assertSame(
            'https://example.com/blog/post.html?page=2',
            UrlResolver::resolve($this->base, '?page=2#top')
        );
    }

    /**
     * A bare "?" is a defined-but-empty query, which RFC 3986 section 5.3
     * recomposes as a trailing "?" on the base path. It does not inherit the
     * base's own query string, and it is distinct from having no query at
     * all: some servers route /path and /path? differently.
     */
    public function testBareQuestionMarkKeepsTheBasePathWithAnEmptyQuery(): void
    {
        self::assertSame(
            'https://example.com/blog/post.html?',
            UrlResolver::resolve($this->base, '?')
        );
    }

    public function testRelativePathKeepsAnExplicitlyEmptyQuery(): void
    {
        self::assertSame(
            'https://example.com/blog/a?',
            UrlResolver::resolve($this->base, 'a?')
        );
    }

    public function testHrefWithNoQueryGainsNoQuestionMark(): void
    {
        self::assertSame(
            'https://example.com/blog/a',
            UrlResolver::resolve($this->base, 'a')
        );
    }

    /**
     * The relative branch used to concatenate origin + raw path unvalidated
     * while the absolute branch round-tripped through Url and returned null,
     * so the same browser-fetchable link got two different verdicts and the
     * relative form later failed Url::fromString() inside Fetcher.
     */
    public function testPercentEncodesARelativeReference(): void
    {
        self::assertSame(
            'https://example.com/annual%20report.pdf?q=a%20b',
            UrlResolver::resolve($this->base, '/annual report.pdf?q=a b')
        );
    }

    public function testPercentEncodesAnAbsoluteHrefInsteadOfDroppingIt(): void
    {
        self::assertSame(
            'https://example.com/annual%20report.pdf',
            UrlResolver::resolve($this->base, 'https://example.com/annual report.pdf')
        );
    }

    public function testRootEscapeIsNoOp(): void
    {
        self::assertSame(
            'https://example.com/etc',
            UrlResolver::resolve($this->base, '../../../etc')
        );
    }

    /**
     * Spec defect 6: these used to be emitted as "mailto://example.com".
     *
     * @return list<array{string}>
     */
    public static function unresolvableHrefs(): array
    {
        return [
            [''],
            ['   '],
            ['#top'],
            ['mailto:someone@example.com'],
            ['tel:+15551234'],
            ['javascript:void(0)'],
            ['JavaScript:void(0)'],
            ['data:text/plain;base64,AAAA'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unresolvableHrefs')]
    public function testRejectsNonHttpHrefs(string $href): void
    {
        self::assertNull(UrlResolver::resolve($this->base, $href));
    }
}
