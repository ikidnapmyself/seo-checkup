<?php

namespace SEOCheckup\Tests\Checks;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Tests\Support\AnalyzeFactory;

final class ContentChecksTest extends TestCase
{
    /**
     * Spec defect 4: division by zero on an empty body.
     */
    public function testCodeContentHandlesAnEmptyBody(): void
    {
        $data = AnalyzeFactory::make('')->codeContent()['data'];

        self::assertSame(0, $data['page_size']);
        self::assertSame(0, $data['content_size']);
        self::assertSame('0%', $data['percentage']);
    }

    /**
     * Spec defect 4.
     */
    public function testPageCompressionHandlesAnEmptyBody(): void
    {
        $data = AnalyzeFactory::make('')->pageCompression()['data'];

        self::assertSame(0.0, $data['actual']);
        self::assertSame(0.0, $data['percentage']);
    }

    public function testCodeContentExcludesScriptAndStyleFromContent(): void
    {
        $data = AnalyzeFactory::make(
            '<html><head><style>.a{color:red}</style></head>'
            . '<body><script>var x=1;</script><p>Visible text</p></body></html>'
        )->codeContent()['data'];

        self::assertStringContainsString('Visible text', $data['content']);
        self::assertStringNotContainsString('color:red', $data['content']);
        self::assertStringNotContainsString('var x', $data['content']);
        self::assertGreaterThan(0, $data['code_size']);
    }

    /**
     * Entity-derived angle brackets survive literally in the content field.
     * Document::text() extracts text nodes only, so &lt;div&gt; becomes literal <div>.
     * This is intentional: visible page text that looks like markup should be counted
     * in the content ratio, not stripped away as if it were actual HTML.
     */
    public function testCodeContentPreservesEntityDerivedAngleBrackets(): void
    {
        $data = AnalyzeFactory::make(
            '<html><body><p>Look at this: &lt;div class="x"&gt;hello&lt;/div&gt;</p></body></html>'
        )->codeContent()['data'];

        self::assertStringContainsString('<div class="x">', $data['content']);
        self::assertStringContainsString('hello', $data['content']);
        self::assertStringContainsString('</div>', $data['content']);
    }

    public function testPageCompressionReportsSavings(): void
    {
        $data = AnalyzeFactory::make(
            '<html><body>' . str_repeat('<p>repeated</p>', 200) . '</body></html>'
        )->pageCompression()['data'];

        self::assertGreaterThan(0, $data['actual']);
        self::assertLessThan($data['actual'], $data['possible']);
        self::assertGreaterThan(0, $data['difference']);
    }

    public function testPlaintextEmailFindsAddressesInVisibleText(): void
    {
        $data = AnalyzeFactory::make(
            '<html><body><p>Write to hello@example.com please.</p></body></html>'
        )->plaintextEmail()['data'];

        self::assertSame(['hello@example.com'], array_values($data));
    }

    public function testPlaintextEmailIgnoresScriptAndStyleContent(): void
    {
        $data = AnalyzeFactory::make(
            '<html><head><style>/* css@example.com */</style></head>'
            . '<body><script>var m = "js@example.com";</script><p>ok</p></body></html>'
        )->plaintextEmail()['data'];

        self::assertSame([], array_values($data));
    }

    /**
     * The document is shared now; a mutating check would poison its neighbours.
     */
    public function testContentChecksDoNotMutateTheSharedDocument(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body><script src="/a.js"></script><p>text</p></body></html>'
        );

        $analyze->codeContent();
        $analyze->plaintextEmail();

        self::assertSame(['/a.js'], $analyze->objectCount()['data']['script']);
    }
}
