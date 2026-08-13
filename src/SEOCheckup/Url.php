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

        $path = $parts['path'] ?? '';

        return new self(
            $scheme,
            strtolower($parts['host']),
            $parts['port'] ?? null,
            $path === '' ? '/' : $path,
            $parts['query'] ?? '',
        );
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
