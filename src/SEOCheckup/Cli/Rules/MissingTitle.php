<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class MissingTitle implements Rule
{
    public function name(): string
    {
        return 'missing-title';
    }

    public function check(): string
    {
        return 'metaTitle';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (!is_string($data) || $data === '') {
            return Verdict::fail('no <title>');
        }

        return Verdict::pass('title present');
    }
}
