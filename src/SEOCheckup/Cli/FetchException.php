<?php

namespace SEOCheckup\Cli;

/**
 * A page could not be fetched. Exit code 2 like a usage error, but without
 * the --help hint — the flags were fine.
 */
final class FetchException extends \RuntimeException
{
}
