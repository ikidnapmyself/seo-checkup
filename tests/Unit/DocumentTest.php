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

    public function testPreservesUtf8WithoutCharsetDeclaration(): void
    {
        $document = new Document('<html><body><p>café naïve 中文</p></body></html>');

        $text = $document->text();

        self::assertStringContainsString('café', $text);
        self::assertStringContainsString('naïve', $text);
        self::assertStringContainsString('中文', $text);
    }

    public function testPreservesUtf8WithCharsetDeclaration(): void
    {
        $document = new Document('<html><head><meta charset="utf-8"></head><body><p>café naïve 中文</p></body></html>');

        $text = $document->text();

        self::assertStringContainsString('café', $text);
        self::assertStringContainsString('naïve', $text);
        self::assertStringContainsString('中文', $text);
    }

    public function testDoesNotInjectNodes(): void
    {
        $document = new Document('<html><head><meta name="description" content="x"></head><body><p>café</p></body></html>');

        // Should still be exactly 1 meta tag, not injected ones
        self::assertSame(1, $document->tags('meta')->length);
    }

    /**
     * libxml never decodes entities inside <style>, <script> or comments, so
     * the numeric-entity pre-pass that keeps UTF-8 alive elsewhere used to
     * leave literal "&#NNN;" sequences in exactly those nodes — visible in
     * inlineCss(), googleAnalytics()'s inline scripts and cache()'s comments.
     */
    public function testKeepsRawTextOfStyleScriptAndCommentsIntact(): void
    {
        $document = new Document(
            '<html><head><style>a::after{content:"→ é"}</style></head>'
            . '<body><!-- cache: café --><p>naïve</p><script>var s = "中文";</script></body></html>'
        );

        self::assertSame('a::after{content:"→ é"}', $document->tags('style')->item(0)?->textContent);
        self::assertSame('var s = "中文";', $document->tags('script')->item(0)?->textContent);
        $comment = $document->xpath()->query('//comment()');
        self::assertNotFalse($comment);
        self::assertInstanceOf(\DOMComment::class, $comment->item(0));
        self::assertSame(' cache: café ', $comment->item(0)->textContent);
        self::assertStringContainsString('naïve', $document->text());
    }

    /**
     * A UTF-8 byte-order mark is common on Windows/CMS-authored pages. It is
     * not whitespace, so trim() left it in place before <!DOCTYPE>, and the
     * parser then treated it as body text: <head> collapsed into <body> and
     * U+FEFF leaked into text().
     */
    public function testStripsALeadingUtf8Bom(): void
    {
        $document = new Document(
            "\xEF\xBB\xBF<!DOCTYPE html><html><head><title>T</title></head><body><h1>H</h1></body></html>"
        );

        self::assertSame('head', $document->tags('title')->item(0)?->parentNode?->nodeName);
        self::assertStringNotContainsString("\u{FEFF}", $document->text());
        self::assertSame(1, $document->tags('title')->length);
    }
}
