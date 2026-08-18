<?php

namespace SEOCheckup\Tests\Support;

use SEOCheckup\DnsLookup;

final class FakeDnsLookup implements DnsLookup
{
    /**
     * @param array<int, array<string, mixed>> $records
     */
    public function __construct(private readonly array $records = [])
    {
    }

    /** @var list<string> */
    public array $queried = [];

    public function txtRecords(string $host): array
    {
        $this->queried[] = $host;

        return $this->records;
    }
}
