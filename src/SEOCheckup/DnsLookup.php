<?php

namespace SEOCheckup;

interface DnsLookup
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function txtRecords(string $host): array;
}
