<?php

namespace SEOCheckup;

use SEOCheckup\Exception\InvalidUrlException;
use Stringable;

final readonly class Url implements Stringable
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param string $userInfo "user" or "user:pass", percent-encoded, or ''
     *                         when the URL carried no credentials
     */
    public function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
        public string $path,
        public string $query,
        public string $userInfo = '',
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

        $host = self::normalizeHost($parts['host']);
        self::validateHost($host, $url);

        $path = self::encode($parts['path'] ?? '');

        // Kept so Fetcher sends URL-embedded Basic-auth credentials, as
        // master did by handing the raw URL to Guzzle. parse_url() splits
        // them out, so "@" cannot appear in either half.
        $userInfo = self::encode($parts['user'] ?? '');

        if (isset($parts['pass'])) {
            $userInfo .= ':' . self::encode($parts['pass']);
        }

        return new self(
            $scheme,
            $host,
            $parts['port'] ?? null,
            $path === '' ? '/' : $path,
            self::encode($parts['query'] ?? ''),
            $userInfo,
        );
    }

    /**
     * Percent-encodes whatever RFC 3986 does not allow in a path or query —
     * a space, a bracket, a stray non-hex "%" — and leaves unreserved,
     * sub-delimiter and already-encoded characters alone, so it is
     * idempotent. This is what every browser and Guzzle's own Uri do on the
     * way out; a URL that merely needs encoding is fetchable, not malformed.
     * The character class matches GuzzleHttp\Psr7\Uri's.
     */
    public static function encode(string $component): string
    {
        return (string) preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-.~!$&\'()*+,;=%:@\/?]++|%(?![A-Fa-f0-9]{2}))/',
            static fn (array $match): string => rawurlencode($match[0]),
            $component
        );
    }

    /**
     * Lower-cases the host and, when ext-intl is available, converts a
     * Unicode (IDN) host to its punycode A-label so it can be looked up and
     * sent on the wire. Without intl the U-label is kept as-is and left to
     * the transport, which is what master did.
     */
    private static function normalizeHost(string $host): string
    {
        // Locale-insensitive since PHP 8.2: multibyte sequences pass through
        // untouched, so this cannot corrupt a U-label.
        $host = strtolower($host);

        if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7f]/', $host) === 1) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii !== false) {
                return $ascii;
            }
        }

        return $host;
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

        // DNS labels: letters, digits and underscore, hyphens allowed inside
        // but not at either end, dot-separated, optional trailing dot for a
        // fully qualified name. Punycode (xn--) fits. Underscore is invalid
        // per RFC 1123 but widely deployed and accepted by every transport
        // this library sits on. \p{L}/\p{N} keeps a U-label acceptable when
        // ext-intl is missing and normalizeHost() could not convert it.
        $label = '[\p{L}\p{N}_](?:[\p{L}\p{N}_-]*[\p{L}\p{N}_])?';

        if (!preg_match('/^(?:' . $label . '\.)*' . $label . '\.?$/u', $host)) {
            throw new InvalidUrlException(sprintf('Invalid hostname format: "%s".', $url));
        }
    }

    /**
     * scheme://host[:port] — the RFC 6454 origin, which by definition
     * excludes credentials. This is what same-site comparisons and the
     * well-known /robots.txt and /favicon.ico probes are built on.
     */
    public function origin(): string
    {
        return $this->scheme . '://' . $this->hostAndPort();
    }

    /**
     * [userinfo@]host[:port] — the RFC 3986 authority, which a relative
     * reference inherits whole (section 5.2.2), credentials included.
     */
    public function authority(): string
    {
        return ($this->userInfo === '' ? '' : $this->userInfo . '@') . $this->hostAndPort();
    }

    private function hostAndPort(): string
    {
        if ($this->port !== null && $this->port !== self::DEFAULT_PORTS[$this->scheme]) {
            return $this->host . ':' . $this->port;
        }

        return $this->host;
    }

    public function __toString(): string
    {
        return $this->scheme . '://' . $this->authority()
            . $this->path . ($this->query === '' ? '' : '?' . $this->query);
    }
}
