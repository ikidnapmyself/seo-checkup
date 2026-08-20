<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class DeprecatedHtml implements Rule
{
    public function name(): string
    {
        return 'deprecated-html';
    }

    public function check(): string
    {
        return 'deprecatedHtml';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (is_array($data) && $data !== []) {
            return Verdict::fail('deprecated tags: ' . implode(', ', array_keys($data)));
        }

        return Verdict::pass('no deprecated tags');
    }
}
