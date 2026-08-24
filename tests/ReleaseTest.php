<?php

namespace SEOCheckup\Tests;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Cli\Application;

/** Repo hygiene: things that must stay in step across a release. */
final class ReleaseTest extends TestCase
{
    public function testVersionMatchesTheChangelog(): void
    {
        $changelog = (string) file_get_contents(__DIR__ . '/../CHANGELOG.md');
        self::assertSame(1, preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $m), 'no released version in CHANGELOG.md');
        self::assertSame($m[1] ?? '', Application::VERSION, 'Application::VERSION must match the newest CHANGELOG entry');
    }
}
