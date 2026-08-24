<?php

namespace SEOCheckup\Cli;

use GuzzleHttp\Client;
use SEOCheckup\Analyze;
use SEOCheckup\Exception\SeoCheckupException;

/**
 * The command: argv → Config → one Runner pass per page → a Renderer →
 * stdout or --output, and an exit code CI can act on.
 */
final class Application
{
    public const VERSION = '1.2.0';

    public const EXIT_OK     = 0;
    public const EXIT_FAILED = 1;
    public const EXIT_USAGE  = 2;

    private const CONNECT_TIMEOUT = 5.0;

    /** @var \Closure(string, int): Analyze */
    private readonly \Closure $factory;

    /**
     * @param (callable(string, int): Analyze)|null $factory (url, timeout seconds) → Analyze.
     *        null builds a Guzzle client honouring --timeout; tests inject a fake.
     */
    public function __construct(?callable $factory = null)
    {
        $this->factory = $factory !== null ? $factory(...) : self::defaultFactory(...);
    }

    /**
     * @param list<string> $argv   including argv[0]
     * @param resource     $stdout
     * @param resource     $stderr
     */
    public function run(array $argv, $stdout, $stderr): int
    {
        try {
            $options = ArgvParser::parse(array_slice($argv, 1));

            if ($options->version) {
                fwrite($stdout, 'seo-checkup ' . self::VERSION . "\n");

                return self::EXIT_OK;
            }

            if ($options->help) {
                fwrite($stdout, self::usage());

                return self::EXIT_OK;
            }

            $config = Config::build($options, $options->config);
            $runner = new Runner(fn (string $url): Analyze => ($this->factory)($url, $config->timeout));

            $pages  = [];
            $failed = false;

            foreach ($config->pages() as $url => $settings) {
                try {
                    $result = $runner->run($url, $settings);
                } catch (SeoCheckupException $e) {
                    throw new FetchException("Could not fetch {$url}: {$e->getMessage()}", 0, $e);
                }

                $pages[] = $result;
                $failed  = $failed || $result->failed;
            }

            /** @var array<string, string> $rendered format+tty => report */
            $rendered = [];

            foreach ($config->sinks as $sink) {
                // Colour only a text report going to a terminal; a file always gets plain text.
                $tty = $sink->isStdout() && stream_isatty($stdout);
                $key = $sink->format . ($tty ? ':tty' : '');

                $rendered[$key] ??= self::renderer($sink->format, $tty)->render($pages, $failed);

                if ($sink->isStdout()) {
                    fwrite($stdout, $rendered[$key]);
                } elseif (@file_put_contents($sink->target, $rendered[$key]) === false) {
                    throw new UsageException("Could not write {$sink->target}");
                }
            }

            return $failed ? self::EXIT_FAILED : self::EXIT_OK;
        } catch (FetchException $e) {
            fwrite($stderr, "seo-checkup: {$e->getMessage()}\n");

            return self::EXIT_USAGE;
        } catch (UsageException $e) {
            fwrite($stderr, "seo-checkup: {$e->getMessage()}\n");
            fwrite($stderr, "Run 'seo-checkup --help' for usage.\n");

            return self::EXIT_USAGE;
        }
    }

    private static function renderer(string $format, bool $tty): Report\Renderer
    {
        return match ($format) {
            'json'  => new Report\JsonRenderer(),
            'md'    => new Report\MarkdownRenderer(),
            default => new Report\TextRenderer(color: $tty),
        };
    }

    private static function defaultFactory(string $url, int $timeout): Analyze
    {
        return new Analyze($url, new Client([
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout'         => (float) $timeout,
            'http_errors'     => false,
        ]));
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
          --text=FILE                Also write the text report to FILE ("-" = stdout)
          --md=FILE                  Also write the Markdown report to FILE ("-" = stdout)
          --json=FILE                Also write the JSON report to FILE ("-" = stdout)
          --config=FILE              Config file; default: ./seo-checkup.json if present
          --timeout=N                Seconds per request; default: 15
          --help                     Show this help
          --version                  Show the version

        Exit codes: 0 all fail-on rules passed · 1 a fail-on rule failed · 2 usage / fetch error

        TXT;
    }
}
