<?php

namespace SEOCheckup;

use SEOCheckup\Exception\InvalidUrlException;
use Stringable;

final readonly class Url implements Stringable
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
        public string $path,
        public string $query,
    ) {
    }

    public static function fromString(string $url): self
    {
        $parts = parse_url(trim($url));

        if ($parts === false) {
            throw new InvalidUrlException(sprintf('Malformed URL: "%s".', $url));
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (!isset(self::DEFAULT_PORTS[$scheme])) {
            throw new InvalidUrlException(
                sprintf('Only http and https URLs are supported, got "%s".', $url)
            );
        }

        if (!isset($parts['host']) || $parts['host'] === '') {
            throw new InvalidUrlException(sprintf('URL has no host: "%s".', $url));
        }

        $host = strtolower($parts['host']);
        self::validateHost($host, $url);

        $path = $parts['path'] ?? '';
        self::validatePath($path, $url);

        return new self(
            $scheme,
            $host,
            $parts['port'] ?? null,
            $path === '' ? '/' : $path,
            $parts['query'] ?? '',
        );
    }

    private static function validateHost(string $host, string $url): void
    {
        // Reject hosts with whitespace
        if (preg_match('/\s/', $host)) {
            throw new InvalidUrlException(sprintf('Host contains whitespace: "%s".', $url));
        }

        // IPv6 in brackets
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return;
        }

        // IPv4 addresses
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return;
        }

        // localhost
        if ($host === 'localhost') {
            return;
        }

        // DNS labels: alphanumeric and hyphen, but not starting/ending with hyphen
        // Allow multiple labels separated by dots
        // Allow punycode (xn-- prefix)
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $host)) {
            throw new InvalidUrlException(sprintf('Invalid hostname format: "%s".', $url));
        }
    }

    private static function validatePath(string $path, string $url): void
    {
        // Reject paths with whitespace
        if (preg_match('/\s/', $path)) {
            throw new InvalidUrlException(sprintf('Path contains whitespace: "%s".', $url));
        }
    }

    public function origin(): string
    {
        $origin = $this->scheme . '://' . $this->host;

        if ($this->port !== null && $this->port !== self::DEFAULT_PORTS[$this->scheme]) {
            $origin .= ':' . $this->port;
        }

        return $origin;
    }

    public function __toString(): string
    {
        return $this->origin() . $this->path . ($this->query === '' ? '' : '?' . $this->query);
    }
}
