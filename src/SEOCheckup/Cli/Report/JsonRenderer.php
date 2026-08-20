<?php

namespace SEOCheckup\Cli\Report;

/**
 * Machine-readable report: verdicts plus the raw check envelopes, pretty printed.
 */
final class JsonRenderer implements Renderer
{
    public function render(array $pages, bool $failed): string
    {
        $out = ['failed' => $failed, 'pages' => []];
        foreach ($pages as $p) {
            $verdicts = [];
            foreach ($p->verdicts as $v) {
                $verdicts[] = [
                    'rule'     => $v->rule,
                    'result'   => $v->result,
                    'message'  => $v->message,
                    'failsRun' => $v->failsRun,
                ];
            }
            $out['pages'][] = [
                'url'      => $p->url,
                'status'   => $p->status,
                'verdicts' => $verdicts,
                'checks'   => $p->checks,
            ];
        }

        return json_encode(
            $out,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . "\n";
    }
}
