<?php
namespace SEOCheckup;

class Helpers
{
    /**
     * @var array $data
     */
    private $data;

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
     * @return array
     */
    public function GetLinks($dom)
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

        return array_unique($links);
    }
}