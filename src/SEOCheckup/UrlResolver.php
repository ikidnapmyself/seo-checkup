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
     *
     * Removes dot segments (. and ..) from a path, normalizing it.
     * Uses the proper RFC algorithm which preserves empty segments (for trailing slashes
     * and double slashes) and treats excess `..` at the root as a no-op.
     */
    private static function removeDotSegments(string $path): string
    {
        $inputBuffer = $path;
        $output = [];

        while ($inputBuffer !== '') {
            // A: If input begins with "../" or "./", remove that prefix
            if (str_starts_with($inputBuffer, '../')) {
                $inputBuffer = substr($inputBuffer, 3);
            } elseif (str_starts_with($inputBuffer, './')) {
                $inputBuffer = substr($inputBuffer, 2);
            }
            // B: If input begins with "/./" or is "/..", replace with "/"
            elseif (str_starts_with($inputBuffer, '/./')) {
                $inputBuffer = '/' . substr($inputBuffer, 3);
            } elseif ($inputBuffer === '/.') {
                $inputBuffer = '/';
            }
            // C: If input begins with "/../" or is "/..", replace with "/" and pop output
            elseif (str_starts_with($inputBuffer, '/../')) {
                $inputBuffer = '/' . substr($inputBuffer, 4);
                array_pop($output);  // No-op if output is empty (at root)
            } elseif ($inputBuffer === '/..') {
                $inputBuffer = '/';
                array_pop($output);  // No-op if output is empty (at root)
            }
            // D: If input is "." or "..", remove it
            elseif ($inputBuffer === '.' || $inputBuffer === '..') {
                $inputBuffer = '';
            }
            // E: Move first path segment to output
            else {
                if (str_starts_with($inputBuffer, '/')) {
                    // Starts with "/", find the next "/" (don't include it)
                    $pos = strpos($inputBuffer, '/', 1);
                    if ($pos === false) {
                        $segEnd = strlen($inputBuffer);
                    } else {
                        $segEnd = $pos;
                    }
                } else {
                    // Doesn't start with "/", find the first "/" (don't include it)
                    $pos = strpos($inputBuffer, '/');
                    if ($pos === false) {
                        $segEnd = strlen($inputBuffer);
                    } else {
                        $segEnd = $pos;
                    }
                }
                $output[] = substr($inputBuffer, 0, $segEnd);
                $inputBuffer = substr($inputBuffer, $segEnd);
            }
        }

        return implode('', $output);
    }
}
