<?php
namespace SEOCheckup;

/**
 * @package seo-checkup
 * @author  Burak <burak@myself.com>
 */

use GuzzleHttp\Client;
use DOMDocument;
use DOMXPath;

class Analyze
{

    /**
     * @var array $data
     */
    private $data;

    private $guzzle;

    /**
     * @var DOMDocument $dom
     */
    private $dom;

    /**
     * Initialize from URL via Guzzle
     *
     * @param string $url
     * @return $this
     */
    public function __construct($url)
    {
        $this->guzzle = new Client;
        $response     = $this->guzzle->get($url);

        $this->data   = [
            'url'        => $url,
            'parsed_url' => parse_url($url),
            'status'     => $response->getStatusCode(),
            'headers'    => $response->getHeaders(),
            'content'    => $response->getBody()->getContents()
        ];

        return $this;
    }

    /**
     * Initialize DOMDocument
     *
     * @return DOMDocument
     */
    private function DOMDocument()
    {
        libxml_use_internal_errors(true);

        $this->dom = new DOMDocument();

        return $this->dom;
    }

    /**
     * Initialize DOMXPath
     *
     * @return DOMXPath
     */
    private function DOMXPath()
    {
        return new DOMXPath($this->dom);
    }

    /**
     * Standardizes output
     *
     * @param $return
     * @return array
     */
    private function Output($return)
    {
        return [
            'url'       => $this->data['url'],
            'status'    => $this->data['status'],
            'headers'   => $this->data['headers'],
            'service'   => preg_replace("([A-Z])", " $0", __FUNCTION__),
            'time'      => time(),
            'data'      => $return
        ];
    }
}