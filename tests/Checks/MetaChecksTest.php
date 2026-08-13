<?php

namespace SEOCheckup\Tests\Checks;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Tests\Support\AnalyzeFactory;

final class MetaChecksTest extends TestCase
{
    /**
     * Spec defect 8: this returned the string "canonical", never the href.
     */
    public function testCanonicalTagReturnsTheHref(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><head><link rel="canonical" href="https://example.com/real"></head></html>'
        );

        self::assertSame('https://example.com/real', $analyze->canonicalTag()['data']);
    }

    public function testCanonicalTagResolvesARelativeHref(): void
    {
        $analyze = AnalyzeFactory::make('<html><head><link rel="canonical" href="/real"></head></html>');

        self::assertSame('https://example.com/real', $analyze->canonicalTag()['data']);
    }

    public function testCanonicalTagIsEmptyWhenAbsent(): void
    {
        self::assertSame('', AnalyzeFactory::make('<html><head></head></html>')->canonicalTag()['data']);
    }

    /**
     * Spec defect 7: in_array() against the whole content string missed this.
     *
     * @return list<array{string, bool, bool}>
     */
    public static function robotsDirectives(): array
    {
        return [
            ['index, follow', false, false],
            ['index, nofollow', true, false],
            ['noindex, follow', false, true],
            ['NOINDEX, NOFOLLOW', true, true],
            ['noindex', false, true],
            ['nofollow', true, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('robotsDirectives')]
    public function testRobotsDirectivesAreParsedAsAList(
        string $content,
        bool $nofollow,
        bool $noindex
    ): void {
        $analyze = AnalyzeFactory::make(
            sprintf('<html><head><meta name="robots" content="%s"></head></html>', $content)
        );

        self::assertSame($nofollow, $analyze->nofollowTag()['data']);
        self::assertSame($noindex, $analyze->noindexTag()['data']);
    }

    public function testRobotsDirectivesAreFalseWhenTheTagIsAbsent(): void
    {
        $analyze = AnalyzeFactory::make('<html><head></head></html>');

        self::assertFalse($analyze->nofollowTag()['data']);
        self::assertFalse($analyze->noindexTag()['data']);
    }

    public function testMetaTitleAndDescription(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><head><title>The Title</title>'
            . '<meta name="Description" content="The description."></head></html>'
        );

        self::assertSame('The Title', $analyze->metaTitle()['data']);
        self::assertSame('The description.', $analyze->metaDescription()['data']);
    }

    public function testMetaTitleAndDescriptionAreEmptyWhenAbsent(): void
    {
        $analyze = AnalyzeFactory::make('<html><head></head></html>');

        self::assertSame('', $analyze->metaTitle()['data']);
        self::assertSame('', $analyze->metaDescription()['data']);
    }

    public function testHeadingsAreCollected(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body><h1>One</h1><h1>Two</h1><h2>Sub</h2></body></html>'
        );

        self::assertSame(['One', 'Two'], $analyze->header1()['data']);
        self::assertSame(['Sub'], $analyze->header2()['data']);
    }

    public function testImageAltSeparatesImagesWithoutAlt(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body><img src="a.png" alt="A"><img src="b.png"></body></html>'
        );

        $data = $analyze->imageAlt()['data'];

        self::assertCount(2, $data['images']);
        self::assertSame(['b.png'], $data['without_alt']);
    }

    public function testDeprecatedHtmlCountsLegacyTags(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body><center>x</center><font>y</font><font>z</font></body></html>'
        );

        self::assertSame(['center' => 1, 'font' => 2], $analyze->deprecatedHtml()['data']);
    }

    public function testFramesetCountsFrames(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><frameset><frame src="a.html"><frame src="b.html"></frameset></html>'
        );

        self::assertSame(['frameset' => 1, 'frame' => 2], $analyze->frameset()['data']);
    }

    public function testObjectCountGroupsAssets(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><head><link rel="stylesheet" href="/a.css"><script src="/a.js"></script></head>'
            . '<body><img src="/a.png"><script>inline()</script></body></html>'
        );

        $data = $analyze->objectCount()['data'];

        self::assertSame(['/a.css'], $data['css']);
        self::assertSame(['/a.js'], $data['script']);
        self::assertSame(['/a.png'], $data['img']);
    }

    public function testInlineCssIsCollected(): void
    {
        $analyze = AnalyzeFactory::make('<html><head><style>body {  color: red; }</style></head></html>');

        self::assertSame(['body { color: red; }'], $analyze->inlineCss()['data']);
    }
}
