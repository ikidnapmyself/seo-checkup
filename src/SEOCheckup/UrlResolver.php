<?php

namespace SEOCheckup;

use GuzzleHttp\Psr7\UriResolver;

final class UrlResolver
{
    public static function resolve(Url $base, string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        // Strip the fragment; it never affects what gets fetched.
        $href = explode('#', $href, 2)[0];

        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $href = $base->scheme . ':' . $href;
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $href, $matches) === 1) {
            $scheme = strtolower($matches[1]);

            if ($scheme !== 'http' && $scheme !== 'https') {
                return null;
            }

            try {
                return (string) Url::fromString($href);
            } catch (Exception\InvalidUrlException) {
                return null;
            }
        }

        // explode() always yields at least one element, so $path is a string.
        $parts = explode('?', $href, 2);
        $path  = $parts[0];
        $query = $parts[1] ?? null;

        if ($path === '' && $query !== null) {
            // RFC 3986 section 5.3: a reference with an empty path takes the
            // base path unchanged. "?page=2" is a new query on the same
            // document, not a sibling of its directory.
            $target = $base->path;
        } elseif (str_starts_with($path, '/')) {
            $target = UriResolver::removeDotSegments($path);
        } else {
            // RFC 3986 section 5.2.4, from the required guzzlehttp/psr7. Its
            // handling of a rootless path that pops past its start ("foo/..")
            // differs from the RFC's, but every path handed to it here begins
            // with "/", so that branch is never reached.
            $target = UriResolver::removeDotSegments(self::directoryOf($base->path) . $path);
        }

        // Encoded here rather than round-tripped through Url::fromString(),
        // which cannot represent the defined-but-empty query below. After
        // encoding, a valid base plus this path and query is always a valid
        // URL, so the two branches agree: nothing fetchable is dropped.
        // The base's whole authority is inherited, credentials included
        // (RFC 3986 section 5.2.2), so a relative Location on an
        // authenticated page keeps working.
        $resolved = $base->scheme . '://' . $base->authority() . Url::encode($target);

        // A defined-but-empty query keeps its "?" — RFC 3986 section 5.3
        // recomposes it, and it is distinct from having no query at all.
        return $query === null ? $resolved : $resolved . '?' . Url::encode($query);
    }

    private static function directoryOf(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? '/' : substr($path, 0, $slash + 1);
    }
}
