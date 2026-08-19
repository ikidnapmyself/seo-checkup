<?php

namespace SEOCheckup\Cli\Report;

/**
 * Renders a check's `data` as plain indented lines — shared by the
 * Markdown and text renderers so the two never disagree about shape.
 */
final class DataFormatter
{
    public const MAX_TEXT = 500;

    /**
     * Lines (no trailing newline) describing $data, nested levels indented by two spaces.
     */
    public static function lines(mixed $data, int $indent = 0): string
    {
        $pad = str_repeat('  ', $indent);
        if (is_array($data)) {
            if ($data === []) {
                return $pad . '(none)';
            }
            $out = [];
            $isList = array_is_list($data);
            foreach ($data as $k => $v) {
                $label = $isList ? '-' : "{$k}:";
                if (is_array($v) && $v !== []) {
                    $out[] = "{$pad}{$label}";
                    $out[] = self::lines($v, $indent + 1);
                } else {
                    $out[] = "{$pad}{$label} " . self::scalar($v);
                }
            }

            return implode("\n", $out);
        }

        return $pad . self::scalar($data);
    }

    /**
     * One-line rendering of a scalar: bools as true/false, null, empty as "", whitespace collapsed, long text truncated.
     */
    public static function scalar(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if ($v === null) {
            return 'null';
        }
        if (is_array($v)) {
            return '(none)';
        }
        $s = is_scalar($v) ? (string) $v : get_debug_type($v);
        $s = preg_replace('/\s+/u', ' ', trim($s)) ?? $s;
        if ($s === '') {
            return '""';
        }
        if (mb_strlen($s) > self::MAX_TEXT) {
            $s = mb_substr($s, 0, self::MAX_TEXT) . '… (truncated)';
        }

        return $s;
    }
}
