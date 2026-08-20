<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class MissingCanonical implements Rule
{
    public function name(): string
    {
        return 'missing-canonical';
    }

    public function check(): string
    {
        return 'canonicalTag';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (!is_string($data) || $data === '') {
            return Verdict::fail('no canonical tag');
        }

        return Verdict::pass('canonical present');
    }
}
