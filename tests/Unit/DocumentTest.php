<?php

namespace SEOCheckup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Document;

final class DocumentTest extends TestCase
{
    public function testParsesOnceAndReturnsTheSameInstance(): void
    {
        $document = new Document('<html><body><p>hi</p></body></html>');

        self::assertSame($document->dom(), $document->dom());
        self::assertSame($document->xpath(), $document->xpath());
    }

    public function testFindsTagsByName(): void
    {
        $document = new Document('<html><body><p>a</p><p>b</p></body></html>');

        self::assertSame(2, $document->tags('p')->length);
    }

    public function testTextExcludesScriptAndStyleWithoutMutating(): void
    {
        $document = new Document(
            '<html><head><style>.a{color:red}</style></head>'
            . '<body>Hello <script>var x = 1;</script>world</body></html>'
        );

        $text = $document->text();

        self::assertStringContainsString('Hello', $text);
        self::assertStringContainsString('world', $text);
        self::assertStringNotContainsString('color:red', $text);
        self::assertStringNotContainsString('var x', $text);

        // The nodes are still there — this is the trap the spec calls out.
        self::assertSame(1, $document->tags('script')->length);
        self::assertSame(1, $document->tags('style')->length);
    }

    public function testHandlesEmptyHtmlWithoutWarnings(): void
    {
        $document = new Document('');

        self::assertSame(0, $document->tags('p')->length);
        self::assertSame('', trim($document->text()));
    }

    public function testSuppressesLibxmlErrorsForHtml5Tags(): void
    {
        $document = new Document('<html><body><main><section>x</section></main></body></html>');

        self::assertSame(1, $document->tags('main')->length);
    }
}
