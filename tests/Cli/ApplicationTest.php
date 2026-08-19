<?php

namespace SEOCheckup\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Cli\Application;

final class ApplicationTest extends TestCase
{
    /** @return array{int, string, string} exit code, stdout, stderr */
    private function runApp(string ...$args): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        \assert($out !== false && $err !== false);

        $code = (new Application())->run(['seo-checkup', ...array_values($args)], $out, $err);

        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }

    public function testVersionPrintsAndExitsZero(): void
    {
        [$code, $out] = $this->runApp('--version');
        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/^seo-checkup \d+\.\d+\.\d+/', $out);
    }

    public function testHelpPrintsUsageAndExitsZero(): void
    {
        [$code, $out] = $this->runApp('--help');
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: seo-checkup <url>', $out);
        self::assertStringContainsString('--fail-on', $out);
    }

    public function testMissingUrlIsAUsageErrorExitTwo(): void
    {
        [$code, , $err] = $this->runApp();
        self::assertSame(2, $code);
        self::assertStringContainsString('<url> is required', $err);
    }

    public function testUnknownOptionIsAUsageErrorExitTwo(): void
    {
        [$code, , $err] = $this->runApp('https://example.com', '--bogus');
        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown option: --bogus', $err);
    }
}
