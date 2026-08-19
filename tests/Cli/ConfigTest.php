<?php

namespace SEOCheckup\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Cli\Config;
use SEOCheckup\Cli\Options;
use SEOCheckup\Cli\UsageException;

final class ConfigTest extends TestCase
{
    private const FILE = __DIR__ . '/../fixtures/seo-checkup.json';

    public function testDefaultsWhenNoFileAndNoFlags(): void
    {
        $c = Config::build(new Options(url: 'https://example.com'), null);

        self::assertSame('https://example.com', $c->url);
        self::assertSame([], $c->paths);
        self::assertNull($c->checks);
        self::assertSame([], $c->failOn);
        self::assertSame('text', $c->format);
        self::assertNull($c->output);
        self::assertSame(15, $c->timeout);
    }

    public function testFileFillsWhatFlagsLeftUnset(): void
    {
        $c = Config::build(new Options(url: 'https://cli.example'), self::FILE);

        self::assertSame('https://cli.example', $c->url, 'flag wins');
        self::assertSame(['/', '/blog/post'], $c->paths);
        self::assertSame(['meta'], $c->checks);
        self::assertSame(['broken-links', 'missing-title', 'missing-description', 'missing-canonical', 'not-https'], $c->failOn);
    }

    public function testFlagsOverrideFile(): void
    {
        $c = Config::build(new Options(url: 'https://x', paths: ['/a'], checks: ['links'], failOn: ['none']), self::FILE);

        self::assertSame(['/a'], $c->paths);
        self::assertSame(['links'], $c->checks);
        self::assertSame([], $c->failOn);
    }

    public function testNoPathsMeansJustTheUrl(): void
    {
        $c = Config::build(new Options(url: 'https://example.com/base/page'), null);

        self::assertSame(['https://example.com/base/page'], array_keys($c->pages()));
    }

    public function testPagesResolvePathsAgainstUrl(): void
    {
        $c = Config::build(new Options(url: 'https://example.com/base/', paths: ['/', 'about', '/blog/']), null);

        self::assertSame(
            ['https://example.com/', 'https://example.com/base/about', 'https://example.com/blog/'],
            array_keys($c->pages()),
        );
    }

    public function testOverridesApplyByPathGlob(): void
    {
        $c = Config::build(new Options(url: 'https://example.com'), self::FILE);
        $pages = $c->pages();

        self::assertSame(['meta'], $pages['https://example.com/']->checks);
        self::assertSame(['links'], $pages['https://example.com/blog/post']->checks);
        self::assertSame(['broken-links'], $pages['https://example.com/blog/post']->failOn);
        self::assertSame(['broken-links', 'missing-title', 'missing-description', 'missing-canonical', 'not-https'], $pages['https://example.com/']->failOn);
    }

    public function testOverridesMatchTheResolvedPathNotTheRawOne(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'seo');
        \assert($tmp !== false);
        file_put_contents($tmp, '{"overrides": {"/base/*": {"fail-on": ["noindex"]}}}');

        try {
            $c = Config::build(new Options(url: 'https://example.com/base/', paths: ['about', '/base/x?q=1']), $tmp);
            $pages = $c->pages();

            self::assertSame(['noindex'], $pages['https://example.com/base/about']->failOn);
            self::assertSame(['noindex'], $pages['https://example.com/base/x?q=1']->failOn);
        } finally {
            unlink($tmp);
        }
    }

    public function testMissingExplicitFileIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Config file not found');
        Config::build(new Options(url: 'https://x'), '/nonexistent.json');
    }

    public function testInvalidJsonIsAUsageError(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'seo');
        \assert($tmp !== false);
        file_put_contents($tmp, '{not json');

        try {
            $this->expectException(UsageException::class);
            $this->expectExceptionMessage('Config file is not valid JSON');
            Config::build(new Options(url: 'https://x'), $tmp);
        } finally {
            unlink($tmp);
        }
    }

    public function testUrlFromFileWhenFlagMissing(): void
    {
        $c = Config::build(new Options(), self::FILE);

        self::assertSame('https://file.example', $c->url);
    }

    public function testNoUrlAnywhereIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('<url> is required');
        Config::build(new Options(), null);
    }

    public function testInvalidUrlIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Invalid URL');
        Config::build(new Options(url: 'not a url'), null)->pages();
    }
}
