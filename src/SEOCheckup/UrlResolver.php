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
     * RFC 3986 section 5.2.4.
     */
    private static function removeDotSegments(string $path): string
    {
        $output = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($output);
                continue;
            }

            $output[] = $segment;
        }

        $result = implode('/', $output);

        return str_starts_with($result, '/') ? $result : '/' . $result;
    }
}
