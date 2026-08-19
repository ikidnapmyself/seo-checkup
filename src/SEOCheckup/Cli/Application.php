<?php

namespace SEOCheckup\Cli;

final class Application
{
    public const VERSION = '1.1.0';

    public const EXIT_OK     = 0;
    public const EXIT_FAILED = 1;
    public const EXIT_USAGE  = 2;

    /**
     * @param list<string> $argv   including argv[0]
     * @param resource     $stdout
     * @param resource     $stderr
     */
    public function run(array $argv, $stdout, $stderr): int
    {
        try {
            $options = ArgvParser::parse(array_slice($argv, 1));
        } catch (UsageException $e) {
            fwrite($stderr, "seo-checkup: {$e->getMessage()}\n");
            fwrite($stderr, "Run 'seo-checkup --help' for usage.\n");

            return self::EXIT_USAGE;
        }

        if ($options->version) {
            fwrite($stdout, 'seo-checkup ' . self::VERSION . "\n");

            return self::EXIT_OK;
        }

        if ($options->help) {
            fwrite($stdout, self::usage());

            return self::EXIT_OK;
        }

        // A later task replaces this with the real run.
        fwrite($stdout, "not implemented\n");

        return self::EXIT_OK;
    }

    public static function usage(): string
    {
        return <<<TXT
        Usage: seo-checkup <url> [options]

          --paths=/,/about           Extra paths resolved against <url>; default: just <url>
          --checks=meta,links,https  Groups and/or check names; default: all (network group
                                     skipped for localhost / *.local / *.test hosts)
          --fail-on=broken-links,…   Rules or presets (all, recommended, none) that fail the run
          --format=text|md|json      Output format; default: text
          --output=FILE              Write the report to FILE instead of stdout
          --config=FILE              Config file; default: ./seo-checkup.json if present
          --timeout=N                Seconds per request; default: 15
          --help                     Show this help
          --version                  Show the version

        Exit codes: 0 all fail-on rules passed · 1 a fail-on rule failed · 2 usage / fetch error

        TXT;
    }
}
