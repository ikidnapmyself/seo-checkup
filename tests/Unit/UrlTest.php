<?php

namespace SEOCheckup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Exception\InvalidUrlException;
use SEOCheckup\Url;

final class UrlTest extends TestCase
{
    public function testParsesComponents(): void
    {
        $url = Url::fromString('https://example.com:8443/blog/post?id=7#top');

        self::assertSame('https', $url->scheme);
        self::assertSame('example.com', $url->host);
        self::assertSame(8443, $url->port);
        self::assertSame('/blog/post', $url->path);
        self::assertSame('id=7', $url->query);
    }

    public function testDefaultsEmptyPathToRoot(): void
    {
        self::assertSame('/', Url::fromString('https://example.com')->path);
    }

    public function testOriginOmitsDefaultPort(): void
    {
        self::assertSame('https://example.com', Url::fromString('https://example.com/x')->origin());
        self::assertSame('http://example.com:8080', Url::fromString('http://example.com:8080/x')->origin());
    }

    public function testToStringDropsTheFragment(): void
    {
        self::assertSame(
            'https://example.com/a?b=1',
            (string) Url::fromString('https://example.com/a?b=1#frag')
        );
    }

    public function testRejectsUrlWithoutHost(): void
    {
        $this->expectException(InvalidUrlException::class);
        Url::fromString('/just/a/path');
    }

    public function testRejectsNonHttpScheme(): void
    {
        $this->expectException(InvalidUrlException::class);
        Url::fromString('ftp://example.com/file');
    }

    public function testRejectsHostWithSpace(): void
    {
        $this->expectException(InvalidUrlException::class);
        Url::fromString('http://example .com/path');
    }

    public function testRejectsPathWithSpace(): void
    {
        $this->expectException(InvalidUrlException::class);
        Url::fromString('https://example.com/path with space');
    }

    public function testAcceptsHostWithHyphensAndMultipleLabels(): void
    {
        $url = Url::fromString('https://my-api.sub-domain.example.com/test');

        self::assertSame('my-api.sub-domain.example.com', $url->host);
    }
}
