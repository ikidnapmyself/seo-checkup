<?php

namespace SEOCheckup\Cli;

/**
 * Everything the report needs about one page.
 */
final class PageResult
{
    /**
     * @param array<string, array<string, mixed>> $checks   Analyze method => envelope, in catalogue order
     * @param array<string, PageVerdict>          $verdicts rule name => verdict, in catalogue order
     * @param bool                                $failed   a fail-on rule failed on this page
     */
    public function __construct(
        public readonly string $url,
        public readonly int $status,
        public readonly array $checks,
        public readonly array $verdicts,
        public readonly bool $failed,
    ) {
    }
}
