<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class UnderscoredLinks implements Rule
{
    public function name(): string
    {
        return 'underscored-links';
    }

    public function check(): string
    {
        return 'underscoredLinks';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (is_array($data) && $data !== []) {
            return Verdict::fail(count($data) . ' link(s) with underscores');
        }

        return Verdict::pass('no underscored links');
    }
}
