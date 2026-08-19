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
     * @param array<string, array{checks?: list<string>|null, fail-on?: list<string>}> $overrides path glob => settings
     */
    private function __construct(
        public readonly string $url,
        public readonly array $paths,
        public readonly ?array $checks,
        public readonly array $failOn,
        public readonly string $format,
        public readonly ?string $output,
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

        return new self(
            url: $url,
            paths: $o->paths ?? self::list($data, 'paths') ?? [],
            checks: $o->checks ?? self::list($data, 'checks'),
            failOn: RuleCatalogue::expand($o->failOn ?? self::list($data, 'fail-on')),
            format: $o->format ?? self::string($data, 'format') ?? 'text',
            output: $o->output,
            timeout: $o->timeout ?? self::int($data, 'timeout') ?? self::DEFAULT_TIMEOUT,
            overrides: self::overrides($data),
        );
    }

    /**
     * Every page to check, with its effective settings after overrides.
     * Paths are resolved against $url per RFC 3986 (UrlResolver): an
     * absolute path replaces the URL's path, a relative one appends.
     * Overrides are matched against the resolved page URL's path (no
     * query), so "about" against https://x/base/ matches "/base/*".
     * No paths = exactly $url, matched against overrides by its own path.
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
                    $failOn = RuleCatalogue::expand($o['fail-on']);
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

    /** @param array<string, mixed> $d */
    private static function int(array $d, string $k): ?int
    {
        return isset($d[$k]) && is_int($d[$k]) && $d[$k] > 0 ? $d[$k] : null;
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
     * @return array<string, array{checks?: list<string>|null, fail-on?: list<string>}>
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
                $entry['checks'] = self::list($o, 'checks');
            }
            if (array_key_exists('fail-on', $o)) {
                $entry['fail-on'] = self::list($o, 'fail-on') ?? [];
            }
            $out[$glob] = $entry;
        }

        return $out;
    }
}
