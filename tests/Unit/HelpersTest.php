<?php

namespace SEOCheckup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Document;
use SEOCheckup\Helpers;
use SEOCheckup\Url;

final class HelpersTest extends TestCase
{
    private Url $base;

    protected function setUp(): void
    {
        $this->base = Url::fromString('https://example.com/blog/post.html');
    }

    public function testResolvesAndDeduplicatesLinks(): void
    {
        $document = new Document(
            '<html><body>'
            . '<a href="/a">1</a>'
            . '<a href="/a">duplicate</a>'
            . '<a href="next.html">2</a>'
            . '<a href="https://other.test/x">3</a>'
            . '</body></html>'
        );

        self::assertSame(
            [
                'https://example.com/a',
                'https://example.com/blog/next.html',
                'https://other.test/x',
            ],
            Helpers::links($document, $this->base)
        );
    }

    /**
     * Spec defect 6.
     */
    public function testDropsNonHttpHrefs(): void
    {
        $document = new Document(
            '<html><body>'
            . '<a href="mailto:someone@example.com">mail</a>'
            . '<a href="tel:+15551234">call</a>'
            . '<a href="#top">jump</a>'
            . '<a href="javascript:void(0)">js</a>'
            . '<a href="/real">real</a>'
            . '</body></html>'
        );

        self::assertSame(['https://example.com/real'], Helpers::links($document, $this->base));
    }

    /**
     * Spec defect 6, second half.
     */
    public function testHonoursBaseHref(): void
    {
        $document = new Document(
            '<html><head><base href="https://cdn.test/assets/"></head>'
            . '<body><a href="x.html">x</a></body></html>'
        );

        self::assertSame(['https://cdn.test/assets/x.html'], Helpers::links($document, $this->base));
    }

    public function testIgnoresMalformedBaseHref(): void
    {
        $document = new Document(
            '<html><head><base href="not a url"></head>'
            . '<body><a href="/x">x</a></body></html>'
        );

        self::assertSame(['https://example.com/x'], Helpers::links($document, $this->base));
    }

    public function testCollectsUniqueAttributes(): void
    {
        $document = new Document(
            '<html><body><img src="a.png"><img src="a.png"><img src="b.png"></body></html>'
        );

        self::assertSame(['a.png', 'b.png'], Helpers::attributes($document, 'img', 'src'));
    }

    public function testCollapsesWhitespace(): void
    {
        self::assertSame(' a b ', Helpers::whitespace("\n a \t\t b \n"));
    }

    /**
     * Finding 1: attributes() must return list<string>, not mixed types.
     * Purely-numeric attribute values should not be coerced to integers.
     */
    public function testAttributesPreservesStringType(): void
    {
        $document = new Document(
            '<html><body>'
            . '<img src="123">'
            . '<img src="0">'
            . '<img src="007">'
            . '</body></html>'
        );

        $result = Helpers::attributes($document, 'img', 'src');
        self::assertSame(['123', '0', '007'], $result);

        // Verify every element is actually a string at runtime, not an int
        // produced by PHP's numeric array-key coercion.
        self::assertSame(['string', 'string', 'string'], array_map('gettype', $result));
    }

    /**
     * Finding 2: baseUrl() must resolve relative <base href> against the page URL.
     */
    public function testHonoursRelativeBaseHref(): void
    {
        $document = new Document(
            '<html><head><base href="/assets/"></head>'
            . '<body><a href="x.html">x</a></body></html>'
        );

        self::assertSame(['https://example.com/assets/x.html'], Helpers::links($document, $this->base));
    }

    /**
     * Finding 2: baseUrl() must resolve relative <base href> correctly with ../ paths.
     */
    public function testHonoursRelativeBaseHrefWithParentDir(): void
    {
        $document = new Document(
            '<html><head><base href="../assets/"></head>'
            . '<body><a href="y.html">y</a></body></html>'
        );

        self::assertSame(['https://example.com/assets/y.html'], Helpers::links($document, $this->base));
    }
}
