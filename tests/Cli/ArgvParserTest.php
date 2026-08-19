<?php

namespace SEOCheckup\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Cli\ArgvParser;
use SEOCheckup\Cli\UsageException;

final class ArgvParserTest extends TestCase
{
    public function testUrlAndDefaults(): void
    {
        $o = ArgvParser::parse(['https://example.com']);
        self::assertSame('https://example.com', $o->url);
        self::assertNull($o->paths);
        self::assertNull($o->checks);
        self::assertNull($o->failOn);
        self::assertNull($o->format);
        self::assertNull($o->output);
        self::assertNull($o->config);
        self::assertNull($o->timeout);
        self::assertFalse($o->help);
        self::assertFalse($o->version);
    }

    public function testListOptionsAreSplitAndTrimmed(): void
    {
        $o = ArgvParser::parse(['https://example.com', '--paths=/, /about', '--checks=meta,links', '--fail-on=all']);
        self::assertSame(['/', '/about'], $o->paths);
        self::assertSame(['meta', 'links'], $o->checks);
        self::assertSame(['all'], $o->failOn);
    }

    public function testScalarOptions(): void
    {
        $o = ArgvParser::parse(['https://example.com', '--format=json', '--output=r.json', '--config=c.json', '--timeout=30']);
        self::assertSame('json', $o->format);
        self::assertSame('r.json', $o->output);
        self::assertSame('c.json', $o->config);
        self::assertSame(30, $o->timeout);
    }

    public function testHelpAndVersionNeedNoUrl(): void
    {
        self::assertTrue(ArgvParser::parse(['--help'])->help);
        self::assertTrue(ArgvParser::parse(['--version'])->version);
    }

    public function testMissingUrlThrows(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('<url> is required');
        ArgvParser::parse([]);
    }

    public function testTwoPositionalsThrow(): void
    {
        $this->expectException(UsageException::class);
        ArgvParser::parse(['https://a.com', 'https://b.com']);
    }

    public function testUnknownOptionThrows(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Unknown option: --bogus');
        ArgvParser::parse(['https://example.com', '--bogus']);
    }

    public function testBadFormatThrows(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('--format must be one of text, md, json');
        ArgvParser::parse(['https://example.com', '--format=xml']);
    }

    public function testBadTimeoutThrows(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('--timeout must be a positive integer');
        ArgvParser::parse(['https://example.com', '--timeout=abc']);
    }
}
