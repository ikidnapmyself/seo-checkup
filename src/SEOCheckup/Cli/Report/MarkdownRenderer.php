<?php

namespace SEOCheckup\Cli\Report;

use SEOCheckup\Cli\Verdict;

/**
 * GitHub-flavoured Markdown: one verdict table per page, raw checks collapsed.
 */
final class MarkdownRenderer implements Renderer
{
    private const ICON = [Verdict::PASS => '✅', Verdict::FAIL => '❌', Verdict::SKIP => '⏭'];

    public function render(array $pages, bool $failed): string
    {
        $md = "# SEO checkup\n\n";
        $failedPages = 0;
        foreach ($pages as $p) {
            $failedPages += $p->failed ? 1 : 0;
            $md .= '## ' . self::escape($p->url) . " — HTTP {$p->status}\n\n";
            $md .= "| Rule | Result | Message | Fails run |\n|---|---|---|---|\n";
            foreach ($p->verdicts as $v) {
                $icon  = self::ICON[$v->result] ?? '';
                $fails = $v->result === Verdict::SKIP ? '—' : ($v->failsRun ? 'yes' : 'no');
                $md .= "| `{$v->rule}` | {$icon} {$v->result} | " . self::cell($v->message) . " | {$fails} |\n";
            }
            $md .= "\n<details><summary>Raw checks</summary>\n\n";
            foreach ($p->checks as $envelope) {
                $data    = $envelope['data'] ?? null;
                $service = self::escape(is_string($envelope['service'] ?? null) ? $envelope['service'] : '');
                $md .= is_array($data) && $data !== []
                    ? "**{$service}**:\n\n" . self::fence(DataFormatter::lines($data)) . "\n"
                    : "**{$service}**: " . self::escape(DataFormatter::scalar($data)) . "\n\n";
            }
            $md .= "</details>\n\n";
        }
        $n = count($pages);
        $md .= "**Result: {$n} page" . ($n === 1 ? '' : 's') . " checked, {$failedPages} failed.**\n";

        return $md;
    }

    private static function cell(string $s): string
    {
        return str_replace(['|', "\r\n", "\r", "\n"], ['\\|', ' ', ' ', ' '], self::escape($s));
    }

    /**
     * Fenced code block — GFM leaves the content's shape alone, so no escaping inside.
     * The fence is one backtick longer than the longest backtick run in the content.
     */
    private static function fence(string $s): string
    {
        $longest = 2;
        preg_match_all('/`+/', $s, $m);
        foreach ($m[0] as $run) {
            $longest = max($longest, strlen($run));
        }
        $fence = str_repeat('`', $longest + 1);

        return "{$fence}\n{$s}\n{$fence}\n";
    }

    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
