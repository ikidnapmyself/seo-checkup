<?php
namespace SEOCheckup;

use GuzzleHttp\Client;

class PreRequirements
{
    /**
     * GET Request via Guzzle
     * @param string $url
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function Request($url)
    {
        $guzzle  = new Client;

        return $guzzle->get($url, ['http_errors' => false]);
    }
}