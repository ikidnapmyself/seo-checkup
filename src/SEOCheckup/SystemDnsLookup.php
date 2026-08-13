<?php

namespace SEOCheckup;

final class SystemDnsLookup implements DnsLookup
{
    public function txtRecords(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        return $records === false ? [] : $records;
    }
}
