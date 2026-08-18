<?php

namespace SEOCheckup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEOCheckup\PageContext;
use SEOCheckup\Url;

final class PageContextTest extends TestCase
{
    /**
     * @param array<string, list<string>> $headers
     */
    private function context(array $headers): PageContext
    {
        return new PageContext(
            'https://example.com/',
            Url::fromString('https://example.com/'),
            200,
            $headers,
            '<html></html>',
            0.25,
        );
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $context = $this->context(['content-type' => ['text/html; charset=utf-8']]);

        self::assertSame('text/html; charset=utf-8', $context->headerLine('Content-Type'));
        self::assertSame('text/html; charset=utf-8', $context->headerLine('CONTENT-TYPE'));
    }

    public function testMissingHeaderIsEmpty(): void
    {
        self::assertSame('', $this->context([])->headerLine('Content-Type'));
        self::assertSame([], $this->context([])->header('Content-Type'));
    }

    public function testKeepsAllValuesOfARepeatedHeader(): void
    {
        $context = $this->context(['Set-Cookie' => ['a=1', 'b=2']]);

        self::assertSame(['a=1', 'b=2'], $context->header('set-cookie'));
    }

    public function testExposesFetchDuration(): void
    {
        self::assertSame(0.25, $this->context([])->fetchDuration);
    }
}
