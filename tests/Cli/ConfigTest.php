<?php

namespace SEOCheckup\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Cli\Config;
use SEOCheckup\Cli\Options;
use SEOCheckup\Cli\UsageException;

final class ConfigTest extends TestCase
{
    private const FILE = __DIR__ . '/../fixtures/seo-checkup.json';

    private string $originalCwd = '';

    private string $tempCwd = '';

    /** load(null) probes ./seo-checkup.json, so run every test from an empty dir. */
    protected function setUp(): void
    {
        $cwd = getcwd();
        \assert($cwd !== false);
        $this->originalCwd = $cwd;
        $this->tempCwd = sys_get_temp_dir() . '/seo-config-' . uniqid();
        mkdir($this->tempCwd);
        chdir($this->tempCwd);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        array_map('unlink', glob($this->tempCwd . '/*') ?: []);
        rmdir($this->tempCwd);
    }

    private function tempJson(string $json): string
    {
        $tmp = tempnam($this->tempCwd, 'cfg');
        \assert($tmp !== false);
        file_put_contents($tmp, $json);

        return $tmp;
    }

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
        self::assertSame('json', $c->format);
        self::assertSame(30, $c->timeout);
    }

    public function testFlagsOverrideFile(): void
    {
        $c = Config::build(new Options(url: 'https://x', paths: ['/a'], checks: ['links'], failOn: ['none'], format: 'md', timeout: 5), self::FILE);

        self::assertSame(['/a'], $c->paths);
        self::assertSame(['links'], $c->checks);
        self::assertSame([], $c->failOn);
        self::assertSame('md', $c->format);
        self::assertSame(5, $c->timeout);
    }

    public function testAutoDiscoversFileInCwd(): void
    {
        file_put_contents($this->tempCwd . '/' . Config::DEFAULT_FILE, '{"url": "https://cwd.example"}');

        self::assertSame('https://cwd.example', Config::build(new Options(), null)->url);
    }

    public function testInvalidFormatInFileIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('format must be one of text, md, json');
        Config::build(new Options(url: 'https://x'), $this->tempJson('{"format": "xml"}'));
    }

    public function testInvalidTimeoutInFileIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('timeout must be a positive integer');
        Config::build(new Options(url: 'https://x'), $this->tempJson('{"timeout": "fast"}'));
    }

    public function testUnknownRuleInOverrideFailsAtBuild(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Unknown rule: bogus');
        Config::build(new Options(url: 'https://x'), $this->tempJson('{"overrides": {"/*": {"fail-on": ["bogus"]}}}'));
    }

    public function testUnknownCheckInFileFailsAtBuild(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Unknown check or group: bogus');
        Config::build(new Options(url: 'https://x'), $this->tempJson('{"checks": ["meta", "bogus"]}'));
    }

    public function testUnknownCheckInAnUnmatchedOverrideFailsAtBuild(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Unknown check or group: bogus');
        Config::build(new Options(url: 'https://x'), $this->tempJson('{"overrides": {"/never-matches/*": {"checks": ["bogus"]}}}'));
    }

    public function testValidGroupNamesInFileAndOverridePassAtBuild(): void
    {
        $c = Config::build(
            new Options(url: 'https://x'),
            $this->tempJson('{"checks": ["meta", "metaTitle"], "overrides": {"/blog/*": {"checks": ["links"]}}}')
        );

        self::assertSame(['meta', 'metaTitle'], $c->checks);
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
        $file = $this->tempJson('{"overrides": {"/base/*": {"fail-on": ["noindex"]}}}');
        $c = Config::build(new Options(url: 'https://example.com/base/', paths: ['about', '/base/x?q=1']), $file);
        $pages = $c->pages();

        self::assertSame(['noindex'], $pages['https://example.com/base/about']->failOn);
        self::assertSame(['noindex'], $pages['https://example.com/base/x?q=1']->failOn);
    }

    public function testMissingExplicitFileIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Config file not found');
        Config::build(new Options(url: 'https://x'), '/nonexistent.json');
    }

    public function testInvalidJsonIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Config file is not valid JSON');
        Config::build(new Options(url: 'https://x'), $this->tempJson('{not json'));
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
