<?php

namespace SEOCheckup\Cli;

/**
 * What the command line said. null means "not given" so Config can fill it.
 */
final class Options
{
    public const FORMATS = ['text', 'md', 'json'];

    /**
     * @param list<string>|null $paths
     * @param list<string>|null $checks
     * @param list<string>|null $failOn
     * @param array<string, string> $sinks format => target ("-" = stdout)
     */
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?array $paths = null,
        public readonly ?array $checks = null,
        public readonly ?array $failOn = null,
        public readonly ?string $format = null,
        public readonly ?string $output = null,
        public readonly ?string $config = null,
        public readonly ?int $timeout = null,
        public readonly array $sinks = [],
        public readonly bool $help = false,
        public readonly bool $version = false,
    ) {
    }
}
