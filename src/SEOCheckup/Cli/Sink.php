<?php

namespace SEOCheckup\Cli;

/**
 * One rendered report and where it goes. A target of "-" means stdout.
 */
final class Sink
{
    public const STDOUT = '-';

    /**
     * @param string $origin the flag this sink came from ("--format", "--output",
     *                       "--text", "--md" or "--json"), so errors can name what
     *                       the user actually typed
     */
    public function __construct(
        public readonly string $format,
        public readonly string $target,
        public readonly string $origin,
    ) {
    }

    public function isStdout(): bool
    {
        return $this->target === self::STDOUT;
    }
}
