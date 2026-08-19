<?php

namespace SEOCheckup\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Cli\PageResult;
use SEOCheckup\Cli\PageVerdict;
use SEOCheckup\Cli\Report\DataFormatter;
use SEOCheckup\Cli\Report\JsonRenderer;
use SEOCheckup\Cli\Report\MarkdownRenderer;
use SEOCheckup\Cli\Report\TextRenderer;
use SEOCheckup\Cli\Verdict;

final class RenderersTest extends TestCase
{
    /** @return list<PageResult> */
    private function pages(): array
    {
        $envelope = fn (mixed $data, string $service) => ['url' => 'https://example.com/', 'status' => 200, 'headers' => [], 'service' => $service, 'time' => 1, 'data' => $data];

        return [new PageResult(
            'https://example.com/',
            200,
            [
                'metaTitle'   => $envelope('Hello', 'Meta Title'),
                'header1'     => $envelope(['One', 'Two'], 'Header1'),
                'imageAlt'    => $envelope(['images' => [['src' => '/a.png', 'alt' => '']], 'without_alt' => ['/a.png']], 'Image Alt'),
                'codeContent' => $envelope(['content' => str_repeat('x', 1000)], 'Code Content'),
                'https'       => $envelope(true, 'Https'),
                'robotsFile'  => $envelope(false, 'Robots File'),
                'header2'     => $envelope([], 'Header2'),
            ],
            [
                'missing-title' => new PageVerdict('missing-title', Verdict::PASS, 'title present', true),
                'multiple-h1'   => new PageVerdict('multiple-h1', Verdict::FAIL, '2 <h1> tags (expected 1)', true),
                'broken-links'  => new PageVerdict('broken-links', Verdict::SKIP, 'check not run', false),
                'noindex'       => new PageVerdict('noindex', Verdict::FAIL, 'page declares noindex', false),
            ],
            true,
        )];
    }

    public function testJsonShape(): void
    {
        $json = (new JsonRenderer())->render($this->pages(), true);
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertTrue($data['failed']);
        self::assertCount(1, $data['pages']);
        self::assertSame('https://example.com/', $data['pages'][0]['url']);
        self::assertSame(200, $data['pages'][0]['status']);
        self::assertSame('Hello', $data['pages'][0]['checks']['metaTitle']['data']);
        self::assertSame(['rule' => 'multiple-h1', 'result' => 'fail', 'message' => '2 <h1> tags (expected 1)', 'failsRun' => true], $data['pages'][0]['verdicts'][1]);
        self::assertStringEndsWith("\n", $json);
    }

    public function testJsonEscapesNothingUnnecessarily(): void
    {
        $json = (new JsonRenderer())->render($this->pages(), true);

        self::assertStringContainsString('"url": "https://example.com/"', $json, 'slashes unescaped, pretty printed');
    }

    public function testMarkdownHasHeadingVerdictTableAndCollapsedRawChecks(): void
    {
        $md = (new MarkdownRenderer())->render($this->pages(), true);

        self::assertStringStartsWith("# SEO checkup\n", $md);
        self::assertStringContainsString('## https://example.com/ — HTTP 200', $md);
        self::assertStringContainsString('| Rule | Result | Message | Fails run |', $md);
        self::assertStringContainsString('| `multiple-h1` | ❌ fail | 2 &lt;h1&gt; tags (expected 1) | yes |', $md);
        self::assertStringContainsString('| `missing-title` | ✅ pass | title present | yes |', $md);
        self::assertStringContainsString('| `broken-links` | ⏭ skip | check not run | — |', $md);
        self::assertStringContainsString('| `noindex` | ❌ fail | page declares noindex | no |', $md);
        self::assertStringContainsString('<details><summary>Raw checks</summary>', $md);
        self::assertStringContainsString('**Meta Title**: Hello', $md);
        self::assertStringContainsString("**Header1**:\n- One\n- Two", $md);
        self::assertStringContainsString('**Https**: true', $md);
        self::assertStringContainsString('**Robots File**: false', $md);
        self::assertStringContainsString('**Header2**: (none)', $md);
        self::assertStringContainsString('… (truncated)', $md, 'long text is truncated');
        self::assertStringContainsString("**Result: 1 page checked, 1 failed.**\n", $md);
    }

    public function testMarkdownEscapesPipesInMessages(): void
    {
        $page = new PageResult('https://example.com/', 200, [], [
            'x' => new PageVerdict('x', Verdict::FAIL, 'a | b', false),
        ], false);
        $md = (new MarkdownRenderer())->render([$page], false);

        self::assertStringContainsString('| `x` | ❌ fail | a \| b | no |', $md);
        self::assertStringContainsString('**Result: 1 page checked, 0 failed.**', $md);
    }

    public function testTextHasNoMarkup(): void
    {
        $txt = (new TextRenderer(color: false))->render($this->pages(), true);

        self::assertStringContainsString("https://example.com/ (HTTP 200)\n", $txt);
        self::assertStringContainsString('FAIL  multiple-h1 [fail-on]: 2 <h1> tags (expected 1)', $txt);
        self::assertStringContainsString('PASS  missing-title [fail-on]: title present', $txt);
        self::assertStringContainsString('SKIP  broken-links: check not run', $txt);
        self::assertStringContainsString('FAIL  noindex: page declares noindex', $txt);
        self::assertStringContainsString("  Meta Title\n    Hello\n", $txt);
        self::assertStringContainsString("  Header1\n    - One\n    - Two\n", $txt);
        self::assertStringContainsString("1 page checked, 1 failed.\n", $txt);
        self::assertStringNotContainsString('|', $txt);
        self::assertStringNotContainsString("\e[", $txt);
    }

    public function testTextColorUsesAnsi(): void
    {
        $txt = (new TextRenderer(color: true))->render($this->pages(), true);

        self::assertStringContainsString("\e[31mFAIL\e[0m", $txt);
        self::assertStringContainsString("\e[32mPASS\e[0m", $txt);
    }

    public function testPluralisation(): void
    {
        $p = fn (bool $failed) => new PageResult('https://example.com/', 200, [], [], $failed);

        self::assertStringContainsString('2 pages checked, 2 failed.', (new MarkdownRenderer())->render([$p(true), $p(true)], true));
        self::assertStringContainsString('0 pages checked, 0 failed.', (new TextRenderer())->render([], false));
    }

    public function testDataFormatter(): void
    {
        self::assertSame('(none)', DataFormatter::lines([]));
        self::assertSame('true', DataFormatter::lines(true));
        self::assertSame('null', DataFormatter::lines(null));
        self::assertSame("- a\n- b", DataFormatter::lines(['a', 'b']));
        self::assertSame("k: v\nn: 3", DataFormatter::lines(['k' => 'v', 'n' => 3]));
        self::assertSame("outer:\n  - x\n  - y\nempty: (none)", DataFormatter::lines(['outer' => ['x', 'y'], 'empty' => []]));
        self::assertSame('a b c', DataFormatter::scalar("a\n  b\tc"), 'whitespace collapsed');
        self::assertSame(DataFormatter::MAX_TEXT + mb_strlen('… (truncated)'), mb_strlen(DataFormatter::scalar(str_repeat('é', 600))));
    }
}
