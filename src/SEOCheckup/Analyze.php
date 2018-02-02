<?php
namespace SEOCheckup;

/**
 * @package seo-checkup
 * @author  Burak <burak@myself.com>
 */

use DOMDocument;
use DOMXPath;

class Analyze extends PreRequirements
{

    /**
     * @var array $data
     */
    private $data;


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
        $response      = $this->Request($url);

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

            $status = $this->Request($link)->getStatusCode();

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

    /**
     * Determines character set from headers
     *
     * @TODO: Use Regex instead of explode
     * @return array
     */
    public function CharacterSet()
    {
        $output = '';

        foreach ($this->data['headers'] as $key => $header)
        {
            if($key == 'Content-Type')
            {
                $output = explode('=', explode(';',$header[0])[1])[1];
            }
        }
        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Calculates code / content percentage
     *
     * @return array
     */
    public function CodeContent()
    {
        $page_size = mb_strlen($this->data['content'], 'utf8');
        $dom       = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $script    = $dom->getElementsByTagName('script');
        $remove    = array();

        foreach ($script as $item)
        {
            $remove[] = $item;
        }

        foreach ($remove as $item)
        {
            $item->parentNode->removeChild($item);
        }

        $page         = $dom->saveHTML();
        $content_size = mb_strlen(strip_tags($page), 'utf8');
        $rate         = (round($content_size / $page_size * 100));
        $output       = array(
            'page_size'     => $page_size,
            'code_size'     => ($page_size - $content_size),
            'content_size'  => $content_size,
            'content'       => $this->helpers->Whitespace(strip_tags($page)),
            'percentage'    => "$rate%"
        );

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Checks deprecated HTML tag usage
     *
     * @return array
     */
    public function DeprecatedHTML()
    {
        $dom       = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $deprecated_tags = array(
            'acronym',
            'applet',
            'basefont',
            'big',
            'center',
            'dir',
            'font',
            'frame',
            'frameset',
            'isindex',
            'noframes',
            's',
            'strike',
            'tt',
            'u'
        );

        $output = array();

        foreach ($deprecated_tags as $tag)
        {
            $tags   = $dom->getElementsByTagName($tag);

            if($tags->length > 0)
            {
                $output[$tag] = $tags->length;
            }
        }

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Determines length of the domain
     *
     * @return array
     */
    public function DomainLength()
    {
        $domain = explode('.',$this->data['parsed_url']['host']);

        array_pop($domain);

        $domain = implode('.',$domain);

        return $this->Output(strlen($domain), __FUNCTION__);
    }

    /**
     * Looks for a favicon
     *
     * @return array
     */
    public function Favicon()
    {
        $ico    = "{$this->data['parsed_url']['scheme']}://{$this->data['parsed_url']['host']}/favicon.ico";
        $link   = '';

        if($this->Request($ico)->getStatusCode() === 200)
        {
            $link   = $ico;
        } else {

            $dom    = $this->DOMDocument();
            $dom->loadHTML($this->data['content']);

            $tags   = $dom->getElementsByTagName('link');
            $fav    = null;

            foreach ($tags as $tag)
            {
                if($tag->getAttribute('rel') == 'shortcut icon' OR $tag->getAttribute('rel') == 'icon')
                {
                    $fav = $tag->getAttribute('href');
                    break;
                }
            }

            if (!filter_var($fav, FILTER_VALIDATE_URL) === false && $this->Request($fav)->getStatusCode() == 200)
            {
                $link = $fav;
            } else if($this->Request($this->data['parsed_url']['scheme'].'://'.$this->data['parsed_url']['host'].'/'.$fav)->getStatusCode() == 200)
            {
                $link = $this->data['parsed_url']['scheme'].'://'.$this->data['parsed_url']['host'].'/'.$fav;
            } else if($this->Request($_GET['value'].'/'.$fav)->getStatusCode() == 200)
            {
                $link = $_GET['value'].'/'.$fav;
            } else {
                $link = '';
            }
        }


        return $this->Output($link, __FUNCTION__);
    }
}