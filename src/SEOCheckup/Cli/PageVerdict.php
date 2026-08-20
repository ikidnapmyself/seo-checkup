<?php

namespace SEOCheckup\Cli;

/**
 * One rule's verdict on one page, plus whether it is in that page's fail-on.
 */
final class PageVerdict
{
    public function __construct(
        public readonly string $rule,
        public readonly string $result,
        public readonly string $message,
        public readonly bool $failsRun,
    ) {
    }
}
