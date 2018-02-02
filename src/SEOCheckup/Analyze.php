<?php
namespace SEOCheckup;

/**
 * @package seo-checkup
 * @author  Burak <burak@myself.com>
 */

use GuzzleHttp\Client;

class Analyze
{

    private $guzzle;

    /**
     * @var string $content
     */
    private $content;

    public function __construct()
    {
        $this->guzzle = new Client;
    }

    /**
     * Initialize from URL via Guzzle
     *
     * @param string $url
     * @return $this
     */
    public function FormURL($url)
    {
        $response = $this->guzzle->get($url);
        $response = $response->getBody()->getContents();

        $this->content = $response;
        return $this;
    }

    /**
     * Initialize with file content
     *
     * @param string $html
     * @return $this
     */
    public function FromHTML($html)
    {
        $this->content = $html;

        return $this;
    }

    /**
     * It could be useful if you try to grab content from URL
     *
     * @return string
     */
    public function GetContent()
    {
        return $this->content;
    }

}