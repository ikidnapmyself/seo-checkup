<?php

namespace SEOCheckup\Cli\Report;

use SEOCheckup\Cli\Verdict;

/**
 * Plain terminal output, optionally ANSI-coloured.
 */
final class TextRenderer implements Renderer
{
    private const LABEL = [Verdict::PASS => 'PASS', Verdict::FAIL => 'FAIL', Verdict::SKIP => 'SKIP'];
    private const COLOR = [Verdict::PASS => '32', Verdict::FAIL => '31', Verdict::SKIP => '90'];

    public function __construct(private readonly bool $color = false)
    {
    }

    public function render(array $pages, bool $failed): string
    {
        $out = '';
        $failedPages = 0;
        foreach ($pages as $p) {
            $failedPages += $p->failed ? 1 : 0;
            $out .= "{$p->url} (HTTP {$p->status})\n";
            foreach ($p->verdicts as $v) {
                $label = $this->paint(self::LABEL[$v->result] ?? strtoupper($v->result), self::COLOR[$v->result] ?? '0');
                $mark  = $v->failsRun && $v->result !== Verdict::SKIP ? ' [fail-on]' : '';
                $out .= "  {$label}  {$v->rule}{$mark}: {$v->message}\n";
            }
            $out .= "\n";
            foreach ($p->checks as $envelope) {
                $service = is_string($envelope['service'] ?? null) ? $envelope['service'] : '';
                $out .= "  {$service}\n";
                $out .= (preg_replace('/^/m', '    ', DataFormatter::lines($envelope['data'] ?? null)) ?? '') . "\n";
            }
            $out .= "\n";
        }
        $n = count($pages);
        $out .= "{$n} page" . ($n === 1 ? '' : 's') . " checked, {$failedPages} failed.\n";

        return $out;
    }

    private function paint(string $s, string $code): string
    {
        return $this->color ? "\e[{$code}m{$s}\e[0m" : $s;
    }
}
