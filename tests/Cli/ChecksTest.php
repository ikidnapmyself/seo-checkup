<?php

namespace SEOCheckup\Tests\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SEOCheckup\Analyze;
use SEOCheckup\Cli\Checks;
use SEOCheckup\Cli\UsageException;

final class ChecksTest extends TestCase
{
    public function testEveryPublicCheckOnAnalyzeIsInExactlyOneGroup(): void
    {
        $methods = array_map(
            fn (\ReflectionMethod $m) => $m->getName(),
            (new \ReflectionClass(Analyze::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        $methods = array_values(array_filter($methods, fn ($m) => $m !== '__construct'));
        sort($methods);

        $all = array_merge(...array_values(Checks::GROUPS));
        sort($all);

        self::assertSame($methods, $all);
        self::assertCount(29, $all);
        self::assertSame($all, array_values(array_unique($all)));
    }

    public function testResolveExpandsGroupsAndNamesInCatalogueOrder(): void
    {
        self::assertSame(
            ['metaTitle', 'https', 'spfRecord', 'domainLength'],
            Checks::resolve(['network', 'metaTitle'], 'https://example.com'),
        );
    }

    public function testResolveNullMeansAll(): void
    {
        self::assertCount(29, Checks::resolve(null, 'https://example.com'));
    }

    /** @return list<array{string}> */
    public static function localHosts(): array
    {
        return [['http://localhost:3000/'], ['http://127.0.0.1/'], ['http://[::1]:8080/'], ['http://app.local/'], ['http://site.test/'], ['http://127.0.0.2/'], ['http://app.localhost/']];
    }

    #[DataProvider('localHosts')]
    public function testNullOnALocalHostSkipsTheNetworkGroup(string $url): void
    {
        $checks = Checks::resolve(null, $url);
        self::assertCount(26, $checks);
        self::assertNotContains('https', $checks);
        self::assertNotContains('spfRecord', $checks);
        self::assertNotContains('domainLength', $checks);
    }

    public function testExplicitNetworkCheckOnLocalHostIsKept(): void
    {
        self::assertSame(['https'], Checks::resolve(['https'], 'http://localhost/'));
    }

    public function testEmptySelectionIsAUsageError(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('checks must name at least one check or group');
        Checks::resolve([], 'https://example.com');
    }

    public function testUnknownNameThrows(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Unknown check or group: bogus');
        Checks::resolve(['bogus'], 'https://example.com');
    }

    public function testIsLocal(): void
    {
        self::assertTrue(Checks::isLocal('http://localhost/'));
        self::assertFalse(Checks::isLocal('https://example.com/'));
        self::assertFalse(Checks::isLocal('https://localhost.example.com/'));
        self::assertFalse(Checks::isLocal('http://127.example.com/'));
    }
}
