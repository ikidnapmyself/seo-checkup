<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class MultipleH1 implements Rule
{
    public function name(): string
    {
        return 'multiple-h1';
    }

    public function check(): string
    {
        return 'header1';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (is_array($data) && count($data) > 1) {
            return Verdict::fail(count($data) . ' <h1> tags (expected 1)');
        }

        return Verdict::pass('at most one <h1>');
    }
}
