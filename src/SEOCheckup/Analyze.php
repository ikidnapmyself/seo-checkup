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

    /**
     * @var Client $guzzle
     */
    private $guzzle;

    /**
     * @var Helpers $helpers
     */
    private $helpers;

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
        $this->guzzle  = new Client;
        $response      = $this->guzzle->get($url);

        $this->data    = [
            'url'        => $url,
            'parsed_url' => parse_url($url),
            'status'     => $response->getStatusCode(),
            'headers'    => $response->getHeaders(),
            'content'    => $response->getBody()->getContents()
        ];

        $this->helpers = new Helpers($this->data);

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
     * @param mixed $return
     * @param string $service
     * @return array
     */
    private function Output($return, $service)
    {
        return [
            'url'       => $this->data['url'],
            'status'    => $this->data['status'],
            'headers'   => $this->data['headers'],
            'service'   => preg_replace("([A-Z])", " $0", $service),
            'time'      => time(),
            'data'      => $return
        ];
    }


    /**
     * Analyze Broken Links in a page
     *
     * @return array
     */
    public function BrokenLinks()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $links  = $this->helpers->GetLinks($dom);
        $scan   = ['errors' => [], 'passed' => []];
        $i      = 0;

        foreach ($links as $key => $link)
        {
            $i++;

            if($i >= 25)
                break;

            $status = $this->guzzle->get($link)->getStatusCode();

            if(substr($status,0,1) > 3 && $status != 999)
                $scan['errors']["HTTP {$status}"][] = $link;
            else
                $scan['passed']["HTTP {$status}"][] = $link;
        }
        return $this->Output([
            'links'   => $links,
            'scanned' => $scan
        ], __FUNCTION__);
    }

    /**
     * Checks header parameters if there is something about cache
     *
     * @return array
     */
    public function Cache()
    {
        $output = ['headers' => [], 'html' => []];

        foreach ($this->data['headers'] as $header)
        {
            foreach ($header as $item)
            {
                if(strpos(mb_strtolower($item),'cache') !== false)
                {
                    $output['headers'][] = $item;
                }
            }
        }

        $dom   = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);
        $xpath = $this->DOMXPath();

        foreach ($xpath->query('//comment()') as $comment)
        {
            if(strpos(mb_strtolower($comment->textContent),'cache') !== false)
            {
                $output['html'][] = '<!-- '.trim($comment->textContent).' //-->';
            }
        }
        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Checks canonical tag
     *
     * @return array
     */
    public function CanonicalTag()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);
        $output = array();
        $links  = $this->helpers->GetAttributes($dom, 'link', 'rel');

        foreach($links as $item)
        {
            if($item == 'canonical')
            {
                $output[] = $item;
            }
        }

        return $this->Output($output, __FUNCTION__);
    }
}