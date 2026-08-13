<?php

namespace SEOCheckup;

use SEOCheckup\Exception\InvalidUrlException;

final class Helpers
{
    /**
     * Unique absolute http(s) links in a page.
     *
     * @return list<string>
     */
    public static function links(Document $document, Url $base): array
    {
        $base  = self::baseUrl($document, $base);
        $links = [];

        foreach ($document->tags('a') as $anchor) {
            $resolved = UrlResolver::resolve($base, $anchor->getAttribute('href'));

            if ($resolved !== null) {
                $links[$resolved] = true;
            }
        }

        return array_keys($links);
    }

    /**
     * @return list<string>
     */
    public static function attributes(Document $document, string $tag = 'a', string $attr = 'href'): array
    {
        $values = [];

        foreach ($document->tags($tag) as $element) {
            $values[$element->getAttribute($attr)] = true;
        }

        return array_map('strval', array_keys($values));
    }

    public static function whitespace(string $input): string
    {
        return preg_replace('!\s+!', ' ', $input) ?? $input;
    }

    public static function baseUrl(Document $document, Url $fallback): Url
    {
        foreach ($document->tags('base') as $element) {
            $href = trim($element->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            // Try to parse as absolute URL first.
            try {
                return Url::fromString($href);
            } catch (InvalidUrlException) {
                // If it fails, try to resolve as relative against the fallback.
                $resolved = UrlResolver::resolve($fallback, $href);
                if ($resolved !== null) {
                    try {
                        return Url::fromString($resolved);
                    } catch (InvalidUrlException) {
                        // Fall through to return fallback.
                    }
                }
            }
        }

        return $fallback;
    }
}
