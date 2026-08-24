<?php

namespace SEOCheckup\Cli;

/**
 * A report could not be written. Exit code 2 like a usage error, but without
 * the --help hint — the flags were fine, the disk was not.
 */
final class OutputException extends \RuntimeException
{
}
