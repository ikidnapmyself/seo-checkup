<?php
namespace SEOCheckup;

/**
 * @package seo-checkup
 * @author  Burak <burak@myself.com>
 */

use GuzzleHttp\Client;

class Analyze
{

    /**
     * @var array $data
     */
    private $data;


    /**
     * Initialize from URL via Guzzle
     *
     * @param string $url
     * @return $this
     */
    public function __construct($url)
    {
        $guzzle   = new Client;
        $response = $guzzle->get($url);

        $this->data = [
            'url'     => $url,
            'status'  => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'content' => $response->getBody()->getContents()
        ];

        return $this;
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