<?php

namespace SEOCheckup\Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testAutoloaderIsWired(): void
    {
        self::assertTrue(class_exists(\SEOCheckup\Helpers::class));
    }

    public function testPhpFloorIsMet(): void
    {
        self::assertGreaterThanOrEqual(80300, \PHP_VERSION_ID);
    }
}
