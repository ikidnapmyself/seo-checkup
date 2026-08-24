<?php

namespace SEOCheckup\Cli;

use SEOCheckup\Exception\InvalidUrlException;
use SEOCheckup\Url;
use SEOCheckup\UrlResolver;

/**
 * Effective settings: flags > seo-checkup.json > defaults.
 */
final class Config
{
    public const DEFAULT_FILE = 'seo-checkup.json';

    public const DEFAULT_TIMEOUT = 15;

    /**
     * @param list<string>      $paths   [] = just $url
     * @param list<string>|null $checks  null = Checks::resolve() default
     * @param list<string>      $failOn  expanded rule names
     * @param list<Sink>        $sinks   in write order; the primary is first
     * @param array<string, array{checks?: list<string>|null, fail-on?: list<string>}> $overrides path glob => settings (fail-on already expanded)
     */
    private function __construct(
        public readonly string $url,
        public readonly array $paths,
        public readonly ?array $checks,
        public readonly array $failOn,
        public readonly string $format,
        public readonly array $sinks,
        public readonly int $timeout,
        private readonly array $overrides,
    ) {
    }

    /**
     * @param string|null $file explicit --config, or null to auto-discover ./seo-checkup.json
     */
    public static function build(Options $o, ?string $file): self
    {
        $data = self::load($file);

        $url = $o->url ?? self::string($data, 'url')
            ?? throw new UsageException('<url> is required (or "url" in ' . ($file ?? self::DEFAULT_FILE) . ')');

        $format = $o->format ?? self::string($data, 'format') ?? 'text';
        if (!in_array($format, Options::FORMATS, true)) {
            throw new UsageException('format must be one of ' . implode(', ', Options::FORMATS));
        }

        $timeout = $o->timeout ?? self::timeout($data) ?? self::DEFAULT_TIMEOUT;

        return new self(
            url: $url,
            paths: $o->paths ?? self::list($data, 'paths') ?? [],
            checks: self::validateChecks($o->checks ?? self::list($data, 'checks')),
            failOn: RuleCatalogue::expand($o->failOn ?? self::list($data, 'fail-on')),
            format: $format,
            sinks: self::sinks($format, $o),
            timeout: $timeout,
            overrides: self::overrides($data),
        );
    }

    /**
     * The primary (--format/--output, default text on stdout) followed by the
     * per-format sinks in a fixed order, so output is deterministic.
     *
     * An identical (format, target) pair asked for twice is the same bytes in
     * the same place, so it collapses to one sink; two *different* formats on
     * one target would clobber each other and is a usage error.
     *
     * @return list<Sink>
     * @throws UsageException on an unknown sink format or a clobbering target
     */
    private static function sinks(string $format, Options $o): array
    {
        foreach (array_keys($o->sinks) as $f) {
            if (!in_array($f, Options::FORMATS, true)) {
                throw new UsageException("Unknown output format: {$f} (expected one of " . implode(', ', Options::FORMATS) . ')');
            }
        }

        $sinks = [new Sink($format, $o->output ?? Sink::STDOUT, $o->output !== null ? '--output' : '--format')];

        foreach (Options::FORMATS as $f) {
            if (isset($o->sinks[$f])) {
                $sinks[] = new Sink($f, $o->sinks[$f], "--{$f}");
            }
        }

        /** @var array<string, list<Sink>> $byTarget */
        $byTarget = [];
        $kept     = [];
        foreach ($sinks as $sink) {
            $same = array_filter($byTarget[$sink->target] ?? [], fn (Sink $s) => $s->format === $sink->format);
            if ($same !== []) {
                continue; // the same report to the same place, asked for twice
            }
            $byTarget[$sink->target][] = $sink;
            $kept[] = $sink;
        }

        foreach ($byTarget as $target => $group) {
            if (count($group) > 1) {
                throw new UsageException(self::collisionMessage((string) $target, $group));
            }
        }

        self::preflight($kept);

        return $kept;
    }

    /**
     * @param non-empty-list<Sink> $group two or more sinks aimed at one target
     */
    private static function collisionMessage(string $target, array $group): string
    {
        $flags = array_map(fn (Sink $s) => $s->origin, $group);
        $last  = array_pop($flags);
        $names = implode(', ', $flags) . ' and ' . $last;
        $where = $group[0]->isStdout() ? 'stdout' : $target;
        $fix   = $group[0]->isStdout() ? 'a file' : 'a different file';

        return count($group) === 2
            ? "{$names} both target {$where}; give one of them {$fix}"
            : "{$names} all target {$where}; give each of them its own file";
    }

    /**
     * Best effort: a target whose directory does not exist can never be
     * written, so say so now rather than after crawling every page and
     * throwing the reports away.
     *
     * @param list<Sink> $sinks
     * @throws UsageException on a target in a directory that does not exist
     */
    private static function preflight(array $sinks): void
    {
        foreach ($sinks as $sink) {
            if ($sink->isStdout()) {
                continue;
            }
            $dir = \dirname($sink->target);
            if (!is_dir($dir)) {
                throw new UsageException("Could not write {$sink->target}: {$dir} is not a directory");
            }
        }
    }

    /**
     * Every page to check, with its effective settings after overrides.
     * Paths are resolved against $url per RFC 3986 (UrlResolver): an
     * absolute path replaces the URL's path, a relative one appends.
     * Overrides are matched against the resolved page URL's path (no
     * query), so "about" against https://x/base/ matches "/base/*".
     * No paths = exactly $url, matched against overrides by its own path.
     *
     * Override fail-on lists were expanded at build() time, so an unknown
     * rule name in the file fails before any page is fetched.
     *
     * @return array<string, PageSettings> page URL => settings
     * @throws UsageException if $url or a resolved page URL is invalid
     */
    public function pages(): array
    {
        try {
            $base = Url::fromString($this->url);
        } catch (InvalidUrlException $e) {
            throw new UsageException("Invalid URL: {$this->url} ({$e->getMessage()})", 0, $e);
        }

        if ($this->paths === []) {
            return [(string) $base => $this->settingsFor((string) $base)];
        }

        $pages = [];
        foreach ($this->paths as $path) {
            $resolved = UrlResolver::resolve($base, $path);
            if ($resolved === null) {
                throw new UsageException("Invalid path: {$path}");
            }
            $pages[$resolved] = $this->settingsFor($resolved);
        }

        return $pages;
    }

    private function settingsFor(string $pageUrl): PageSettings
    {
        $path = parse_url($pageUrl, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $checks = $this->checks;
        $failOn = $this->failOn;
        foreach ($this->overrides as $glob => $o) {
            if (fnmatch($glob, $path)) {
                if (array_key_exists('checks', $o)) {
                    $checks = $o['checks'];
                }
                if (array_key_exists('fail-on', $o)) {
                    $failOn = $o['fail-on'];
                }
            }
        }

        return new PageSettings($checks, $failOn);
    }

    // --- file handling -------------------------------------------------

    /** @return array<string, mixed> */
    private static function load(?string $file): array
    {
        if ($file === null) {
            if (!is_file(self::DEFAULT_FILE)) {
                return [];
            }
            $file = self::DEFAULT_FILE;
        } elseif (!is_file($file)) {
            throw new UsageException("Config file not found: {$file}");
        }

        $json = file_get_contents($file);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data)) {
            throw new UsageException("Config file is not valid JSON: {$file}");
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** @param array<string, mixed> $d */
    private static function string(array $d, string $k): ?string
    {
        return isset($d[$k]) && is_string($d[$k]) ? $d[$k] : null;
    }

    /**
     * Name validation only — every item must be a group or a check name, so a
     * typo in the file or an override fails before anything is fetched.
     * Checks::resolve() still does the real per-page resolution (including
     * the local-host network skip).
     *
     * @param list<string>|null $checks
     * @return list<string>|null
     * @throws UsageException on an unknown or empty selection
     */
    private static function validateChecks(?array $checks): ?array
    {
        if ($checks === null) {
            return null;
        }
        if ($checks === []) {
            throw new UsageException('checks must name at least one check or group');
        }
        foreach ($checks as $item) {
            if (!isset(Checks::GROUPS[$item]) && !in_array($item, Checks::all(), true)) {
                throw new UsageException("Unknown check or group: {$item}");
            }
        }

        return $checks;
    }

    /**
     * @param array<string, mixed> $d
     * @throws UsageException if present but not a positive integer
     */
    private static function timeout(array $d): ?int
    {
        if (!array_key_exists('timeout', $d)) {
            return null;
        }
        $t = $d['timeout'];
        if (!is_int($t) || $t <= 0) {
            throw new UsageException('timeout must be a positive integer');
        }

        return $t;
    }

    /**
     * @param array<string, mixed> $d
     * @return list<string>|null
     */
    private static function list(array $d, string $k): ?array
    {
        if (!isset($d[$k]) || !is_array($d[$k])) {
            return null;
        }

        return array_values(array_filter($d[$k], 'is_string'));
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, array{checks?: list<string>|null, fail-on?: list<string>}> fail-on expanded to rule names
     * @throws UsageException on an unknown rule name
     */
    private static function overrides(array $d): array
    {
        $out = [];
        if (!isset($d['overrides']) || !is_array($d['overrides'])) {
            return $out;
        }
        foreach ($d['overrides'] as $glob => $o) {
            if (!is_string($glob) || !is_array($o)) {
                continue;
            }
            /** @var array<string, mixed> $o */
            $entry = [];
            if (array_key_exists('checks', $o)) {
                $entry['checks'] = self::validateChecks(self::list($o, 'checks'));
            }
            if (array_key_exists('fail-on', $o)) {
                $entry['fail-on'] = RuleCatalogue::expand(self::list($o, 'fail-on') ?? []);
            }
            $out[$glob] = $entry;
        }

        return $out;
    }
}
