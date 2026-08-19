<?php

namespace SEOCheckup\Cli;

final class ArgvParser
{
    private const LIST_OPTIONS   = ['paths', 'checks', 'fail-on'];
    private const STRING_OPTIONS = ['format', 'output', 'config'];

    /**
     * @param list<string> $args argv without argv[0]
     */
    public static function parse(array $args): Options
    {
        $url = null;
        /** @var array<string, string> $values */
        $values = [];
        $help = $version = false;

        foreach ($args as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $help = true;
                continue;
            }
            if ($arg === '--version' || $arg === '-V') {
                $version = true;
                continue;
            }
            if (str_starts_with($arg, '--')) {
                $eq = strpos($arg, '=');
                $name = substr($arg, 2, $eq === false ? null : $eq - 2);
                if (!in_array($name, [...self::LIST_OPTIONS, ...self::STRING_OPTIONS, 'timeout'], true)) {
                    throw new UsageException("Unknown option: --{$name}");
                }
                if ($eq === false) {
                    throw new UsageException("--{$name} needs a value: --{$name}=…");
                }
                $values[$name] = substr($arg, $eq + 1);
                continue;
            }
            if ($url !== null) {
                throw new UsageException("Unexpected argument: {$arg} (only one <url> is allowed)");
            }
            $url = $arg;
        }

        $format = $values['format'] ?? null;
        if ($format !== null && !in_array($format, Options::FORMATS, true)) {
            throw new UsageException('--format must be one of ' . implode(', ', Options::FORMATS));
        }

        $timeout = null;
        if (isset($values['timeout'])) {
            if (!ctype_digit($values['timeout']) || (int) $values['timeout'] < 1) {
                throw new UsageException('--timeout must be a positive integer');
            }
            $timeout = (int) $values['timeout'];
        }

        return new Options(
            url: $url,
            paths: self::list($values, 'paths'),
            checks: self::list($values, 'checks'),
            failOn: self::list($values, 'fail-on'),
            format: $format,
            output: $values['output'] ?? null,
            config: $values['config'] ?? null,
            timeout: $timeout,
            help: $help,
            version: $version,
        );
    }

    /**
     * @param array<string, string> $values
     * @return list<string>|null
     */
    private static function list(array $values, string $name): ?array
    {
        if (!isset($values[$name])) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $values[$name])), fn ($v) => $v !== ''));
    }
}
