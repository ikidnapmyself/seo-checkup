<?php

namespace SEOCheckup;

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

        [$path, $query] = array_pad(explode('?', $href, 2), 2, null);

        $path = str_starts_with($path, '/')
            ? $path
            : self::directoryOf($base->path) . $path;

        $resolved = $base->origin() . self::removeDotSegments($path);

        return $query === null || $query === '' ? $resolved : $resolved . '?' . $query;
    }

    private static function directoryOf(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? '/' : substr($path, 0, $slash + 1);
    }

    /**
     * RFC 3986 section 5.2.4 with root-escape prevention.
     *
     * Removes dot segments (. and ..) from a path, normalizing it.
     * Preserves trailing slashes when the path ends with a dot segment.
     * Prevents escaping above the root: segments after root escape are dropped.
     */
    private static function removeDotSegments(string $path): string
    {
        $segments = explode('/', $path);
        $output = [];
        $lastWasDotSegment = false;
        $escapedAboveRoot = false;

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                // Empty segments come from leading /, trailing /, or double slashes
                // A single . represents the current directory
                $lastWasDotSegment = ($segment === '.');
                continue;
            }

            if ($segment === '..') {
                // Go up one directory
                if (!empty($output)) {
                    array_pop($output);
                } else {
                    // Already at root, trying to escape above
                    $escapedAboveRoot = true;
                }
                $lastWasDotSegment = true;
                continue;
            }

            // Don't add regular segments if we've escaped above root
            if (!$escapedAboveRoot) {
                $output[] = $segment;
            }
            $lastWasDotSegment = false;
        }

        // Rebuild the path with leading slash
        $result = '/' . implode('/', $output);

        // If the path ended with a dot segment or is just the root with trailing slash,
        // ensure it ends with a slash
        if ($lastWasDotSegment && $result !== '/') {
            $result .= '/';
        }

        return $result;
    }
}
