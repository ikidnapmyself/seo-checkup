<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class MissingDescription implements Rule
{
    public function name(): string
    {
        return 'missing-description';
    }

    public function check(): string
    {
        return 'metaDescription';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (!is_string($data) || $data === '') {
            return Verdict::fail('no meta description');
        }

        return Verdict::pass('description present');
    }
}
