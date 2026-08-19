<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class PlaintextEmail implements Rule
{
    public function name(): string
    {
        return 'plaintext-email';
    }

    public function check(): string
    {
        return 'plaintextEmail';
    }

    public function evaluate(mixed $data): Verdict
    {
        if (is_array($data) && $data !== []) {
            return Verdict::fail(count($data) . ' plain-text email address(es) exposed');
        }

        return Verdict::pass('no plain-text emails');
    }
}
