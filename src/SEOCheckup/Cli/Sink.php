<?php

namespace SEOCheckup\Cli;

/**
 * One rendered report and where it goes. A target of "-" means stdout.
 */
final class Sink
{
    public const STDOUT = '-';

    public function __construct(
        public readonly string $format,
        public readonly string $target,
    ) {
    }

    public function isStdout(): bool
    {
        return $this->target === self::STDOUT;
    }
}
