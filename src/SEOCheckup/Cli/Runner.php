<?php

namespace SEOCheckup\Cli;

use SEOCheckup\Analyze;

/**
 * Fetches one page through Analyze, runs the selected checks and evaluates
 * every rule. Rules whose check was not selected are reported as skipped,
 * never as failed, so a narrow --checks cannot trip --fail-on by accident.
 */
final class Runner
{
    /** @var \Closure(string): Analyze */
    private readonly \Closure $factory;

    /**
     * @param callable(string): Analyze $factory builds the Analyze for one page URL;
     *                                           the seam that lets tests inject a fake client
     */
    public function __construct(callable $factory)
    {
        $this->factory = $factory(...);
    }

    /**
     * @throws \SEOCheckup\Exception\SeoCheckupException when the page cannot be fetched
     * @throws UsageException when $settings->checks names an unknown check
     */
    public function run(string $url, PageSettings $settings): PageResult
    {
        $methods = Checks::resolve($settings->checks, $url);
        $analyze = ($this->factory)($url);

        $checks = [];
        foreach ($methods as $method) {
            $envelope = $analyze->{$method}();
            if (!is_array($envelope)) {
                throw new \LogicException("Analyze::{$method}() did not return an envelope");
            }
            /** @var array<string, mixed> $envelope */
            $checks[$method] = $envelope;
        }

        $first  = $methods[0] ?? null;
        $status = $first !== null && is_int($checks[$first]['status'] ?? null) ? $checks[$first]['status'] : 0;

        $verdicts = [];
        $failed   = false;
        foreach (RuleCatalogue::all() as $name => $rule) {
            $failsRun = in_array($name, $settings->failOn, true);
            $verdict  = isset($checks[$rule->check()])
                ? $rule->evaluate($checks[$rule->check()]['data'] ?? null)
                : Verdict::skip();
            $verdicts[$name] = new PageVerdict($name, $verdict->result, $verdict->message, $failsRun);
            if ($failsRun && $verdict->result === Verdict::FAIL) {
                $failed = true;
            }
        }

        return new PageResult($url, $status, $checks, $verdicts, $failed);
    }
}
