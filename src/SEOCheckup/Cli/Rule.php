<?php

namespace SEOCheckup\Cli;

/**
 * A pass/fail judgement over one check's envelope `data`. Rules never
 * throw: an unexpected shape yields a verdict, not an error.
 */
interface Rule
{
    /** Kebab-case, what --fail-on refers to. */
    public function name(): string;

    /** The Analyze method whose envelope `data` this rule reads. */
    public function check(): string;

    public function evaluate(mixed $data): Verdict;
}
