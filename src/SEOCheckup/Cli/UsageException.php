<?php

namespace SEOCheckup\Cli;

/**
 * Bad flags, missing URL, unreadable config. Always exit code 2.
 */
final class UsageException extends \RuntimeException
{
}
