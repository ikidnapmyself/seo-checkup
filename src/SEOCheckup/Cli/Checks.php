<?php

namespace SEOCheckup\Cli;

/**
 * Which Analyze methods to run. Groups are the CLI's vocabulary; the
 * library has no notion of them.
 */
final class Checks
{
    /** @var array<string, list<string>> */
    public const GROUPS = [
        'meta'        => ['metaTitle', 'metaDescription', 'canonicalTag', 'noindexTag', 'nofollowTag', 'robotsFile'],
        'links'       => ['brokenLinks', 'inboundLinks', 'underscoredLinks', 'socialMedia', 'plaintextEmail'],
        'content'     => ['header1', 'header2', 'imageAlt', 'codeContent', 'deprecatedHtml', 'frameset', 'inlineCss', 'objectCount', 'favicon', 'googleAnalytics'],
        'headers'     => ['cache', 'characterSet', 'serverSignature', 'pageCompression'],
        'network'     => ['https', 'spfRecord', 'domainLength'],
        'performance' => ['pageSpeed'],
    ];

    /**
     * null selection = every group, minus 'network' on a local host (https,
     * SPF and domain length say nothing about a dev server). An explicit
     * selection is honoured as given. Output order is catalogue order, so
     * reports are stable regardless of how the flags were typed.
     *
     * @param list<string>|null $selection groups and/or method names
     * @return list<string> Analyze method names
     */
    public static function resolve(?array $selection, string $url): array
    {
        if ($selection === null) {
            $groups = self::GROUPS;
            if (self::isLocal($url)) {
                unset($groups['network']);
            }

            return array_merge(...array_values($groups));
        }
        if ($selection === []) {
            throw new UsageException('checks must name at least one check or group');
        }

        $wanted = [];
        foreach ($selection as $item) {
            if (isset(self::GROUPS[$item])) {
                array_push($wanted, ...self::GROUPS[$item]);
                continue;
            }
            if (!in_array($item, self::all(), true)) {
                throw new UsageException("Unknown check or group: {$item}");
            }
            $wanted[] = $item;
        }

        return array_values(array_filter(self::all(), fn ($m) => in_array($m, $wanted, true)));
    }

    /** @return list<string> every method name, in catalogue order */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /** localhost (and *.localhost), 127.* loopback, ::1 and the .local / .test dev TLDs. */
    public static function isLocal(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = trim($host, '[]');

        return $host === 'localhost'
            || str_starts_with($host, '127.')
            || $host === '::1'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
    }
}
