<?php

namespace SEOCheckup\Cli\Report;

use SEOCheckup\Cli\PageResult;

/**
 * Turns a run's page results into one output document.
 */
interface Renderer
{
    /**
     * @param list<PageResult> $pages
     * @param bool             $failed any page failed — the run's overall verdict
     */
    public function render(array $pages, bool $failed): string;
}
