<?php

namespace SEOCheckup\Cli;

/**
 * The effective checks and fail-on for one page, after overrides.
 */
final class PageSettings
{
    /**
     * @param list<string>|null $checks  null = Checks::resolve() default
     * @param list<string>      $failOn  expanded rule names
     */
    public function __construct(public readonly ?array $checks, public readonly array $failOn)
    {
    }
}
