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
        $started_on    = microtime(true);
        $response      = $this->Request($url);

        $this->data    = [
            'url'        => $url,
            'parsed_url' => parse_url($url),
            'status'     => $response->getStatusCode(),
            'headers'    => $response->getHeaders(),
            'page_speed' => number_format(( microtime(true) - $started_on), 4),
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

    /**
     * Checks if there is a frame in the page
     *
     * @return array
     */
    public function Frameset()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags   = $dom->getElementsByTagName('frameset');
        $output = ['frameset' => [], 'frame' => []];
        foreach ($tags as $tag)
        {
            $output['frameset'][] = null;
        }

        $tags   = $dom->getElementsByTagName('frame');
        foreach ($tags as $tag)
        {
            $output['frame'][] = null;
        }

        return $this->Output([
            'frameset' => count($output['frameset']),
            'frame'    => count($output['frame'])
        ], __FUNCTION__);
    }

    /**
     * Finds Google Analytics code
     *
     * @return array
     */
    public function GoogleAnalytics()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $script = '';

        $tags   = $dom->getElementsByTagName('script');
        foreach ($tags as $tag)
        {
            if($tag->getAttribute('src'))
            {
                if (0 === strpos($tag->getAttribute('src'), '//'))
                {
                    $href     = $this->data['parsed_url']['scheme'] . ':'.$tag->getAttribute('src');
                } else if (0 !== strpos($tag->getAttribute('src'), 'http'))
                {
                    $path     = '/' . ltrim($tag->getAttribute('src'), '/');
                    $href     = $this->data['parsed_url']['scheme'] . '://';

                    if (isset($this->data['parsed_url']['user']) && isset($this->data['parsed_url']['pass']))
                    {
                        $href .= $this->data['parsed_url']['user'] . ':' . $this->data['parsed_url']['pass'] . '@';
                    }

                    $href     .= $this->data['parsed_url']['host'];

                    if (isset($this->data['parsed_url']['port']))
                    {
                        $href .= ':' . $this->data['parsed_url']['port'];
                    }
                    $href    .= $path;
                } else {
                    $href     = $tag->getAttribute('src');
                }

                $script .= $this->Request($href)->getBody()->getContents();
            } else {
                $script .= $tag->nodeValue;
            }
        }

        $ua_regex        = "/UA-[0-9]{5,}-[0-9]{1,}/";

        preg_match_all($ua_regex, $script, $ua_id);

        return $this->Output($ua_id[0][0], __FUNCTION__);
    }

    /**
     * Checks h1 HTML tag usage
     *
     * @return array
     */
    public function Header1()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags   = $dom->getElementsByTagName('h1');
        $output = array();
        foreach ($tags as $tag)
        {
            $output[] = $tag->nodeValue;
        }

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Checks h2 HTML tag usage
     *
     * @return array
     */
    public function Header2()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags   = $dom->getElementsByTagName('h2');
        $output = array();
        foreach ($tags as $tag)
        {
            $output[] = $tag->nodeValue;
        }

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Checks HTTPS
     *
     * @return array
     */
    public function Https()
    {
        $https = ($this->data['parsed_url']['scheme'] === 'https') ? true : false;

        return $this->Output($https, __FUNCTION__);
    }

    /**
     * Checks empty image alts
     *
     * @return array
     */
    public function ImageAlt()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags         = $dom->getElementsByTagName('img');
        $images       = array();
        $errors       = array();

        foreach($tags as $item)
        {
            $src      = $item->getAttribute('src');
            $alt      = $item->getAttribute('alt');

            $images[] = array(
                'src' => $src,
                'alt' => $alt
            );

            if($alt == '')
            {
                $link = $src;

                $errors[] = $link;
            }
        }

        $output       = array(
            'images'        => $images,
            'without_alt'   => $errors
        );

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Gets inbound links
     *
     * @return array
     */
    public function InboundLinks()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags   = $dom->getElementsByTagName('a');
        $output = array();

        foreach($tags as $item)
        {
            $link = $item->getAttribute('href');

            if($link != '' && strpos($link,'#') !== 0)
            {
                $link = parse_url($link);

                if(!isset($link['scheme']))
                {
                    $link['scheme'] = $this->data['parsed_url']['scheme'];
                }

                if(!isset($link['host']))
                {
                    $link['host'] = $this->data['parsed_url']['host'];
                }

                if(!isset($link['path']))
                {
                    $link['path'] = '';
                } else {
                    if(strpos($link['path'],'/') === false)
                    {
                        $link['path'] = '/'.$link['path'];
                    }
                }

                if(!isset($link['query']))
                {
                    $link['query'] = '';
                } else {
                    $link['query'] = '?'.$link['query'];
                }

                $output[] = $link['scheme'].'://'.$link['host'].$link['path'].$link['query'];
            }
        }

        foreach ($output as $key => $link)
        {
            if (parse_url($link)['host'] != $this->data['parsed_url']['host']) {
                unset($output[$key]);
                continue;
            }
        }

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Gets inbound links
     *
     * @return array
     */
    public function InlineCss()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags   = $dom->getElementsByTagName('style');
        $output = array();

        foreach($tags as $item)
        {
            $output[] = $this->helpers->Whitespace($item->textContent);
        }

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Gets meta description
     *
     * @return array
     */
    public function MetaDescription()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);
        $tags   = $dom->getElementsByTagName('meta');
        $output = '';
        foreach ($tags as $tag)
        {
            $content = $tag->getAttribute('content');
            if(strtolower($tag->getAttribute('name')) == 'description' && strlen($content) > 0)
            {
                $output = $content;
            }
        }

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Gets meta title
     *
     * @return array
     */
    public function MetaTitle()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);
        $tags   = $dom->getElementsByTagName('title');
        $output = '';
        foreach ($tags as $tag)
        {
            if(isset($tag->nodeValue) && strlen($tag->nodeValue) > 0)
            {
                $output = $tag->nodeValue;
            }
            break;
        }


        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Gets no-follow tag
     *
     * @return array
     */
    public function NofollowTag()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags   = $dom->getElementsByTagName('meta');
        $output = array();
        foreach ($tags as $tag)
        {
            if($tag->getAttribute('name') == 'robots')
            {
                $output[] = $tag->getAttribute('content');
            }
        }

        return $this->Output(in_array('nofollow',$output), __FUNCTION__);
    }

    /**
     * Gets no-index tag
     *
     * @return array
     */
    public function NoindexTag()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $tags   = $dom->getElementsByTagName('meta');
        $output = array();
        foreach ($tags as $tag)
        {
            if($tag->getAttribute('name') == 'robots')
            {
                $output[] = $tag->getAttribute('content');
            }
        }

        return $this->Output(in_array('noindex',$output), __FUNCTION__);
    }

    /**
     * Counts objects in a page
     *
     * @return array
     */
    public function ObjectCount()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $output = array(
            'css'    => array(),
            'script' => array(),
            'img'    => array()
        );

        $tags   = $dom->getElementsByTagName('link');
        foreach ($tags as $tag)
        {
            if($tag->getAttribute('type') == 'text/css' OR $tag->getAttribute('rel') == 'stylesheet')
            {
                $output['css'][] = $tag->getAttribute('href');
            }
        }
        $output['css']   = array_unique($output['css']);

        $tags   = $dom->getElementsByTagName('script');
        foreach ($tags as $tag)
        {
            if($tag->getAttribute('src') != '')
            {
                $output['script'][] = $tag->getAttribute('src');
            }
        }
        $output['script'] = array_unique($output['script']);

        $tags   = $dom->getElementsByTagName('img');
        foreach ($tags as $tag)
        {
            if($tag->getAttribute('src') != '')
            {
                $output['img'][] = $tag->getAttribute('src');
            }
        }
        $output['img']   = array_unique($output['img']);

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Calculates page speed
     *
     * @return array
     */
    public function PageSpeed()
    {
        return $this->Output($this->data['page_speed'], __FUNCTION__);
    }

    /**
     * Checks if there is some plaintext email
     *
     * @return array
     */
    public function PlaintextEmail()
    {
        $dom    = $this->DOMDocument();
        $dom->loadHTML($this->data['content']);

        $script = $dom->getElementsByTagName('script');
        $remove = array();

        foreach($script as $item)
        {
            $remove[] = $item;
        }

        foreach ($remove as $item)
        {
            $item->parentNode->removeChild($item);
        }
        $style        = $dom->getElementsByTagName('style');
        $remove       = array();

        foreach($style as $item)
        {
            $remove[] = $item;
        }

        foreach ($remove as $item)
        {
            $item->parentNode->removeChild($item);
        }

        $page   = $dom->saveHTML();
        $page   = trim(preg_replace('/<[^>]*>/', ' ', $page));
        $page   = preg_replace('/\s+/', ' ',$page);
        $page   = explode(' ',$page);

        $output = array();
        foreach ($page as $item)
        {
            $item = trim($item);

            if($item != '' && strpos($item,'@') !== false)
            {
                if (!filter_var($item, FILTER_VALIDATE_EMAIL) === false) {
                    $output[] = $item;
                }
            }
        }

        $output = array_unique($output);

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Checks HTML page compression
     *
     * @return array
     */
    public function PageCompression()
    {
        $output               = array();

        $output['actual']     = round(strlen($this->data['content']) / 1024,2);
        $output['possible']   = gzcompress($this->data['content'], 9);
        $output['possible']   = round(strlen($output['possible']) / 1024,2);
        $output['percentage'] = round((($output['possible'] * 100) / $output['actual']),2);
        $output['difference'] = round($output['actual'] - $output['possible'],2);

        return $this->Output($output, __FUNCTION__);
    }

    /**
     * Checks robots.txt
     *
     * @return array
     */
    public function RobotsFile()
    {
        $output = $this->Request("{$this->data['parsed_url']['scheme']}://{$this->data['parsed_url']['host']}/robots.txt");

        if($output->getStatusCode() === 200)
        {
            $output = $output->getBody()->getContents();
        } else {
            $output = false;
        }

        return $this->Output($output, __FUNCTION__);
    }
}