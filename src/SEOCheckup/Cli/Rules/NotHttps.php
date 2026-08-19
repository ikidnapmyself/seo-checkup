<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class NotHttps implements Rule
{
    public function name(): string
    {
        return 'not-https';
    }

    public function check(): string
    {
        return 'https';
    }

    public function evaluate(mixed $data): Verdict
    {
        if ($data === false) {
            return Verdict::fail('page was not served over HTTPS');
        }

        return Verdict::pass('served over HTTPS');
    }
}
