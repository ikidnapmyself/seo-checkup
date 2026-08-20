<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class Noindex implements Rule
{
    public function name(): string
    {
        return 'noindex';
    }

    public function check(): string
    {
        return 'noindexTag';
    }

    public function evaluate(mixed $data): Verdict
    {
        if ($data === true) {
            return Verdict::fail('page declares noindex');
        }

        return Verdict::pass('indexable');
    }
}
