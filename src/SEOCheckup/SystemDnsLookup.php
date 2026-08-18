<?php

namespace SEOCheckup;

final class SystemDnsLookup implements DnsLookup
{
    public function txtRecords(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        if ($records === false) {
            return [];
        }

        // dns_get_record() is typed as returning bare arrays, so each record
        // is rebuilt with string keys rather than asserted to have them.
        $output = [];

        foreach ($records as $record) {
            $fields = [];

            foreach ($record as $key => $value) {
                $fields[(string) $key] = $value;
            }

            $output[] = $fields;
        }

        return $output;
    }
}
