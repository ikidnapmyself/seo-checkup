<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class BrokenLinks implements Rule
{
    public function name(): string
    {
        return 'broken-links';
    }

    public function check(): string
    {
        return 'brokenLinks';
    }

    public function evaluate(mixed $data): Verdict
    {
        $errors = is_array($data) && is_array($data['scanned']['errors'] ?? null)
            ? $data['scanned']['errors']
            : [];
        $count = 0;
        foreach ($errors as $urls) {
            $count += is_array($urls) ? count($urls) : 0;
        }
        if ($count === 0) {
            return Verdict::pass('no broken links among the scanned ones');
        }

        return Verdict::fail("{$count} broken link(s): " . implode(', ', array_keys($errors)));
    }
}
