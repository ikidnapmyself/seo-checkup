<?php

namespace SEOCheckup;

use Psr\Http\Message\ResponseInterface;

/**
 * A response together with the URL it was actually served from.
 *
 * The requested URL and the served URL differ whenever a redirect was
 * followed, and apex -> www and http -> https are the two most common
 * configurations on the web. Everything that reasons about the page's origin
 * — link resolution, the https verdict, the canonical tag — has to use $url,
 * not the string the caller passed in.
 */
final readonly class Fetched
{
    public function __construct(
        public Url $url,
        public ResponseInterface $response,
    ) {
    }
}
