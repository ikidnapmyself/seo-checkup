<?php

namespace SEOCheckup\Cli;

/**
 * What a Rule concluded about one check's data. Immutable value.
 */
final class Verdict
{
    public const PASS = 'pass';
    public const FAIL = 'fail';
    public const SKIP = 'skip';

    private function __construct(public readonly string $result, public readonly string $message)
    {
    }

    public static function pass(string $message = ''): self
    {
        return new self(self::PASS, $message);
    }

    public static function fail(string $message): self
    {
        return new self(self::FAIL, $message);
    }

    public static function skip(string $message = 'check not run'): self
    {
        return new self(self::SKIP, $message);
    }
}
