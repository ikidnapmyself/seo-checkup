<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class MissingH1 implements Rule
{
    public function name(): string
    {
        return 'missing-h1';
    }

    public function check(): string
    {
        return 'header1';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (!is_array($data) || $data === []) {
            return Verdict::fail('no <h1>');
        }

        return Verdict::pass('<h1> present');
    }
}
