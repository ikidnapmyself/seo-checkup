<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class NoRobotsTxt implements Rule
{
    public function name(): string
    {
        return 'no-robots-txt';
    }

    public function check(): string
    {
        return 'robotsFile';
    }

    public function evaluate(mixed $data): Verdict
    {
        if ($data === false) {
            return Verdict::fail('no /robots.txt');
        }

        return Verdict::pass('/robots.txt present');
    }
}
