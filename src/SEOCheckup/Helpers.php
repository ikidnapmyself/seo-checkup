<?php
namespace SEOCheckup;

class Helpers
{
    /**
     * @var array $data
     */
    private $data;

    /**
     * @var array $links
     */
    private $links;

    /**
     * Helpers constructor
     * @param array $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get links in a page
     *
     * @param \DOMDocument $dom
     * @return $this
     */
    public function Links($dom)
    {
        $tags  = $dom->getElementsByTagName('a');
        $links = array();

        if($tags->length)
        {
            foreach($tags as $item)
            {
                $link = $item->getAttribute('href');

                if($link != '' && strpos($link,'#') !== 0 && strpos(strtolower($link),'javascript:') !== 0)
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
                        if(strpos($link['path'],'/')  !== 0)
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

                    $links[] = $link['scheme'].'://'.$link['host'].$link['path'].$link['query'];
                }
            }
        }

        $this->links = array_unique($links);

        return $this;
    }

    /**
     * Return page links
     *
     * @return array
     */
    public function GetLinks()
    {
        return $this->links;
    }

    /**
     * Returns page links by host
     *
     * @param array $links
     * @return array
     */
    public function GetHosts($links = [])
    {
        if(is_array($links) && count($links) > 0)
        {
            $this->links = $links;
        }

        $hosts = [];

        foreach ($this->links as $link)
        {
            $parse = parse_url($link);

            if(isset($parse['host']) && !in_array($parse['host'], $hosts))
            {
                $hosts[] = $parse['host'];
            }
        }

        return $hosts;
    }

    /**
     * Get link attributes in a page
     *
     * @param \DOMDocument $dom
     * @param string $tag
     * @param string $attr
     * @return array
     */
    public function GetAttributes($dom, $tag = 'a', $attr = 'href')
    {
        $tags  = $dom->getElementsByTagName($tag);
        $links = array();

        if($tags->length)
        {
            foreach($tags as $item)
            {
                $links[] = $item->getAttribute($attr);
            }
        }

        return array_unique($links);
    }

    /**
     * Whitespace cleaner
     *
     * @param $input
     * @return null|string|string[]
     */
    public function Whitespace($input)
    {
        return preg_replace('!\s+!', ' ', $input);
    }
}