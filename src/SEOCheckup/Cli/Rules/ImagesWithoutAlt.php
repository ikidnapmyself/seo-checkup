<?php

namespace SEOCheckup\Cli\Rules;

use SEOCheckup\Cli\Rule;
use SEOCheckup\Cli\Verdict;

final class ImagesWithoutAlt implements Rule
{
    public function name(): string
    {
        return 'images-without-alt';
    }

    public function check(): string
    {
        return 'imageAlt';
    }

    public function evaluate(mixed $data): Verdict
    {
        $without = is_array($data) ? ($data['without_alt'] ?? null) : null;
        if (is_array($without) && $without !== []) {
            return Verdict::fail(count($without) . ' image(s) without alt');
        }

        return Verdict::pass('all images have alt');
    }
}
