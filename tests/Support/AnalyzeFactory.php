<?php

namespace SEOCheckup\Tests\Support;

use SEOCheckup\Analyze;
use SEOCheckup\DnsLookup;

final class AnalyzeFactory
{
    public const URL = 'https://example.com/';

    /**
     * @param array<string, string> $headers
     */
    public static function make(
        string $html,
        array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
        ?FakeHttpClient $client = null,
        ?DnsLookup $dns = null,
    ): Analyze {
        $client ??= new FakeHttpClient();
        $client->route(self::URL, $html, 200, $headers);

        return new Analyze(self::URL, $client, $dns ?? new FakeDnsLookup());
    }

    public static function fixture(string $name): string
    {
        $path = __DIR__ . '/../fixtures/' . $name;
        $html = file_get_contents($path);

        if ($html === false) {
            throw new \RuntimeException("Missing fixture: {$path}");
        }

        return $html;
    }
}
