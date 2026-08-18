<?php

namespace SEOCheckup\Tests\Unit;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
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

    /**
     * A space in the path is a browser-fetchable URL, not a malformed one:
     * every client percent-encodes it on the way out. Rejecting it made the
     * library stricter than the transport it fronts.
     */
    public function testPercentEncodesWhitespaceInThePath(): void
    {
        $url = Url::fromString('https://example.com/annual report.pdf');

        self::assertSame('/annual%20report.pdf', $url->path);
        self::assertSame('https://example.com/annual%20report.pdf', (string) $url);
    }

    public function testPercentEncodesWhitespaceInTheQuery(): void
    {
        self::assertSame('q=a%20b', Url::fromString('https://example.com/?q=a b')->query);
    }

    public function testDoesNotDoubleEncodeAnAlreadyEncodedPath(): void
    {
        self::assertSame('/a%20b/c%25zz', Url::fromString('https://example.com/a%20b/c%zz')->path);
    }

    public function testEncodeIsIdempotentAndLeavesReservedCharactersAlone(): void
    {
        self::assertSame('/p/a-b_c.d~e!$&\'()*+,;=:@%20', Url::encode('/p/a-b_c.d~e!$&\'()*+,;=:@ '));
        self::assertSame('a%5B%5D=1&x=y', Url::encode(Url::encode('a[]=1&x=y')));
    }

    public function testAcceptsHostWithHyphensAndMultipleLabels(): void
    {
        $url = Url::fromString('https://my-api.sub-domain.example.com/test');

        self::assertSame('my-api.sub-domain.example.com', $url->host);
    }

    /**
     * Underscored labels are invalid per RFC 1123 but widely deployed
     * (cdn_static.example.com); curl, browsers and Guzzle all fetch them.
     */
    public function testAcceptsUnderscoreInHostLabel(): void
    {
        self::assertSame('cdn_static.example.com', Url::fromString('https://cdn_static.example.com/x')->host);
    }

    public function testAcceptsFullyQualifiedHostWithTrailingDot(): void
    {
        self::assertSame('example.com.', Url::fromString('https://example.com./')->host);
    }

    #[RequiresPhpExtension('intl')]
    public function testConvertsUnicodeHostToPunycode(): void
    {
        $url = Url::fromString('https://München.de/x');

        self::assertSame('xn--mnchen-3ya.de', $url->host);
        self::assertSame('https://xn--mnchen-3ya.de/x', (string) $url);
    }

    public function testStillRejectsLabelStartingWithHyphen(): void
    {
        $this->expectException(InvalidUrlException::class);
        Url::fromString('https://-bad.example.com/');
    }
}
