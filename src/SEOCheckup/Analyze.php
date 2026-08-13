<?php

namespace SEOCheckup;

use DOMComment;
use Psr\Http\Client\ClientInterface;
use SEOCheckup\Exception\RequestFailedException;

/**
 * @package seo-checkup
 * @author  Burak
 */
class Analyze
{
    private readonly Fetcher $fetcher;

    private readonly DnsLookup $dns;

    private readonly PageContext $page;

    private readonly Document $document;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $dnsRecords = null;

    /**
     * @throws Exception\InvalidUrlException
     * @throws RequestFailedException
     */
    public function __construct(string $url, ?ClientInterface $http = null, ?DnsLookup $dns = null)
    {
        $parsed  = Url::fromString($url);
        $this->fetcher = new Fetcher($http);
        $this->dns     = $dns ?? new SystemDnsLookup();

        $startedOn = microtime(true);
        $response  = $this->fetcher->get($url);
        $duration  = microtime(true) - $startedOn;

        // PSR-7 types header values as array<string>; PageContext wants lists.
        $headers = array_map(array_values(...), $response->getHeaders());

        $this->page = new PageContext(
            $url,
            $parsed,
            $response->getStatusCode(),
            $headers,
            (string) $response->getBody(),
            $duration,
        );

        $this->document = new Document($this->page->body);
    }

    /**
     * DNS is only looked up when a check actually needs it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dnsRecords(): array
    {
        return $this->dnsRecords ??= $this->dns->txtRecords($this->page->parsed->host);
    }

    /**
     * @return array{url: string, status: int, headers: array<string, list<string>>, service: string, time: int, data: mixed}
     */
    private function output(mixed $data, string $service): array
    {
        return [
            'url'     => $this->page->url,
            'status'  => $this->page->status,
            'headers' => $this->page->headers,
            'service' => self::label($service),
            'time'    => time(),
            'data'    => $data,
        ];
    }

    /**
     * "brokenLinks" becomes "Broken Links".
     */
    private static function label(string $method): string
    {
        return ucfirst(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $method)));
    }

    /**
     * Analyze Broken Links in a page
     *
     * @return array<string, mixed>
     */
    public function brokenLinks(): array
    {
        $links = Helpers::links($this->document, $this->page->parsed);
        $scan  = ['errors' => [], 'passed' => []];
        $i     = 0;

        foreach ($links as $link) {
            $i++;

            if ($i >= 25) {
                break;
            }

            $status = $this->fetcher->status($link);

            if (substr((string) $status, 0, 1) > 3 && $status != 999) {
                $scan['errors']["HTTP {$status}"][] = $link;
            } else {
                $scan['passed']["HTTP {$status}"][] = $link;
            }
        }

        return $this->output([
            'links'   => $links,
            'scanned' => $scan,
        ], __FUNCTION__);
    }

    /**
     * Anything cache-related in the headers or in HTML comments.
     *
     * @return array<string, mixed>
     */
    public function cache(): array
    {
        $output = ['headers' => [], 'html' => []];

        foreach ($this->page->headers as $key => $values) {
            foreach ($values as $value) {
                if (str_contains(mb_strtolower($key . ' ' . $value), 'cache')) {
                    $output['headers'][] = $value;
                }
            }
        }

        $comments = $this->document->xpath()->query('//comment()');

        if ($comments !== false) {
            foreach ($comments as $comment) {
                if (!$comment instanceof DOMComment) {
                    continue;
                }

                if (str_contains(mb_strtolower($comment->textContent), 'cache')) {
                    $output['html'][] = '<!-- ' . trim($comment->textContent) . ' //-->';
                }
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * The canonical URL declared by the page, resolved to absolute.
     *
     * @return array<string, mixed>
     */
    public function canonicalTag(): array
    {
        $output = '';

        foreach ($this->document->tags('link') as $link) {
            if (strtolower($link->getAttribute('rel')) !== 'canonical') {
                continue;
            }

            $resolved = UrlResolver::resolve(
                Helpers::baseUrl($this->document, $this->page->parsed),
                $link->getAttribute('href')
            );

            if ($resolved !== null) {
                $output = $resolved;
                break;
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Determines character set from the Content-Type header.
     *
     * @return array<string, mixed>
     */
    public function characterSet(): array
    {
        $contentType = $this->page->headerLine('Content-Type');
        $output      = '';

        if (preg_match('/charset\s*=\s*"?([^";,\s]+)"?/i', $contentType, $matches) === 1) {
            $output = $matches[1];
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Calculates code / content percentage
     *
     * @return array<string, mixed>
     */
    public function codeContent(): array
    {
        $page_size    = mb_strlen($this->page->body, 'utf8');
        $page         = Helpers::whitespace($this->document->text());
        $content_size = mb_strlen($page, 'utf8');
        $rate         = (round($content_size / $page_size * 100));
        $output       = [
            'page_size'    => $page_size,
            'code_size'    => ($page_size - $content_size),
            'content_size' => $content_size,
            'content'      => $page,
            'percentage'   => "$rate%",
        ];

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Checks deprecated HTML tag usage
     *
     * @return array<string, mixed>
     */
    public function deprecatedHtml(): array
    {
        $deprecated_tags = [
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
            'u',
        ];

        $output = [];

        foreach ($deprecated_tags as $tag) {
            $tags = $this->document->tags($tag);

            if ($tags->length > 0) {
                $output[$tag] = $tags->length;
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Determines length of the domain
     *
     * @return array<string, mixed>
     */
    public function domainLength(): array
    {
        $domain = explode('.', $this->page->parsed->host);

        array_pop($domain);

        $domain = implode('.', $domain);

        return $this->output(strlen($domain), __FUNCTION__);
    }

    /**
     * Looks for a favicon
     *
     * @return array<string, mixed>
     */
    public function favicon(): array
    {
        $ico  = "{$this->page->parsed->scheme}://{$this->page->parsed->host}/favicon.ico";
        $link = '';

        if ($this->fetcher->status($ico) === 200) {
            $link = $ico;
        } else {
            $tags = $this->document->tags('link');
            $fav  = '';

            foreach ($tags as $tag) {
                if ($tag->getAttribute('rel') == 'shortcut icon' || $tag->getAttribute('rel') == 'icon') {
                    $fav = $tag->getAttribute('href');
                    break;
                }
            }

            $value = (string) ($_GET['value'] ?? '');

            if (!filter_var($fav, FILTER_VALIDATE_URL) === false && $this->fetcher->status($fav) == 200) {
                $link = $fav;
            } elseif ($this->fetcher->status($this->page->parsed->scheme . '://' . $this->page->parsed->host . '/' . $fav) == 200) {
                $link = $this->page->parsed->scheme . '://' . $this->page->parsed->host . '/' . $fav;
            } elseif ($this->fetcher->status($value . '/' . $fav) == 200) {
                $link = $value . '/' . $fav;
            } else {
                $link = '';
            }
        }

        return $this->output($link, __FUNCTION__);
    }

    /**
     * Checks if there is a frame in the page
     *
     * @return array<string, mixed>
     */
    public function frameset(): array
    {
        return $this->output([
            'frameset' => $this->document->tags('frameset')->length,
            'frame'    => $this->document->tags('frame')->length,
        ], __FUNCTION__);
    }

    /**
     * Finds Google Analytics code
     *
     * @return array<string, mixed>
     */
    public function googleAnalytics(): array
    {
        $script = '';

        $tags = $this->document->tags('script');

        foreach ($tags as $tag) {
            if ($tag->getAttribute('src')) {
                if (0 === strpos($tag->getAttribute('src'), '//')) {
                    $href = $this->page->parsed->scheme . ':' . $tag->getAttribute('src');
                } elseif (0 !== strpos($tag->getAttribute('src'), 'http')) {
                    $path = '/' . ltrim($tag->getAttribute('src'), '/');
                    $href = $this->page->parsed->scheme . '://';

                    $href .= $this->page->parsed->host;

                    if ($this->page->parsed->port !== null) {
                        $href .= ':' . $this->page->parsed->port;
                    }

                    $href .= $path;
                } else {
                    $href = $tag->getAttribute('src');
                }

                $script .= $this->fetcher->body($href);
            } else {
                $script .= $tag->nodeValue;
            }
        }

        $ua_regex = "/UA-[0-9]{5,}-[0-9]{1,}/";

        preg_match_all($ua_regex, $script, $ua_id);

        return $this->output($ua_id[0][0], __FUNCTION__);
    }

    /**
     * Checks h1 HTML tag usage
     *
     * @return array<string, mixed>
     */
    public function header1(): array
    {
        $tags   = $this->document->tags('h1');
        $output = [];

        foreach ($tags as $tag) {
            $output[] = $tag->nodeValue;
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Checks h2 HTML tag usage
     *
     * @return array<string, mixed>
     */
    public function header2(): array
    {
        $tags   = $this->document->tags('h2');
        $output = [];

        foreach ($tags as $tag) {
            $output[] = $tag->nodeValue;
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Checks HTTPS
     *
     * @return array<string, mixed>
     */
    public function https(): array
    {
        $https = ($this->page->parsed->scheme === 'https') ? true : false;

        return $this->output($https, __FUNCTION__);
    }

    /**
     * Checks empty image alts
     *
     * @return array<string, mixed>
     */
    public function imageAlt(): array
    {
        $tags   = $this->document->tags('img');
        $images = [];
        $errors = [];

        foreach ($tags as $item) {
            $src = $item->getAttribute('src');
            $alt = $item->getAttribute('alt');

            $images[] = [
                'src' => $src,
                'alt' => $alt,
            ];

            if ($alt == '') {
                $link = $src;

                $errors[] = $link;
            }
        }

        $output = [
            'images'      => $images,
            'without_alt' => $errors,
        ];

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Gets inbound links
     *
     * @return array<string, mixed>
     */
    public function inboundLinks(): array
    {
        $tags   = $this->document->tags('a');
        $output = [];

        foreach ($tags as $item) {
            $link = $item->getAttribute('href');

            if ($link != '' && strpos($link, '#') !== 0) {
                $link = (array) parse_url($link);

                if (!isset($link['scheme'])) {
                    $link['scheme'] = $this->page->parsed->scheme;
                }

                if (!isset($link['host'])) {
                    $link['host'] = $this->page->parsed->host;
                }

                if (!isset($link['path'])) {
                    $link['path'] = '';
                } else {
                    if (strpos($link['path'], '/') === false) {
                        $link['path'] = '/' . $link['path'];
                    }
                }

                if (!isset($link['query'])) {
                    $link['query'] = '';
                } else {
                    $link['query'] = '?' . $link['query'];
                }

                $output[] = $link['scheme'] . '://' . $link['host'] . $link['path'] . $link['query'];
            }
        }

        foreach ($output as $key => $link) {
            if (parse_url($link, PHP_URL_HOST) != $this->page->parsed->host) {
                unset($output[$key]);
                continue;
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Gets inline css
     *
     * @return array<string, mixed>
     */
    public function inlineCss(): array
    {
        $tags   = $this->document->tags('style');
        $output = [];

        foreach ($tags as $item) {
            $output[] = Helpers::whitespace($item->textContent);
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Gets meta description
     *
     * @return array<string, mixed>
     */
    public function metaDescription(): array
    {
        $tags   = $this->document->tags('meta');
        $output = '';

        foreach ($tags as $tag) {
            $content = $tag->getAttribute('content');

            if (strtolower($tag->getAttribute('name')) == 'description' && strlen($content) > 0) {
                $output = $content;
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Gets meta title
     *
     * @return array<string, mixed>
     */
    public function metaTitle(): array
    {
        $tags   = $this->document->tags('title');
        $output = '';

        foreach ($tags as $tag) {
            if (isset($tag->nodeValue) && strlen($tag->nodeValue) > 0) {
                $output = $tag->nodeValue;
            }
            break;
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Gets no-follow tag
     *
     * @return array<string, mixed>
     */
    public function nofollowTag(): array
    {
        return $this->output(in_array('nofollow', $this->robotsDirectives(), true), __FUNCTION__);
    }

    /**
     * Gets no-index tag
     *
     * @return array<string, mixed>
     */
    public function noindexTag(): array
    {
        return $this->output(in_array('noindex', $this->robotsDirectives(), true), __FUNCTION__);
    }

    /**
     * Directives from every <meta name="robots"> tag, lowercased and split.
     *
     * @return list<string>
     */
    private function robotsDirectives(): array
    {
        $directives = [];

        foreach ($this->document->tags('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) !== 'robots') {
                continue;
            }

            foreach (explode(',', $meta->getAttribute('content')) as $directive) {
                $directive = strtolower(trim($directive));

                if ($directive !== '') {
                    $directives[] = $directive;
                }
            }
        }

        return $directives;
    }

    /**
     * Counts objects in a page
     *
     * @return array<string, mixed>
     */
    public function objectCount(): array
    {
        $output = [
            'css'    => [],
            'script' => [],
            'img'    => [],
        ];

        $tags = $this->document->tags('link');

        foreach ($tags as $tag) {
            if ($tag->getAttribute('type') == 'text/css' || $tag->getAttribute('rel') == 'stylesheet') {
                $output['css'][] = $tag->getAttribute('href');
            }
        }
        $output['css'] = array_unique($output['css']);

        $tags = $this->document->tags('script');

        foreach ($tags as $tag) {
            if ($tag->getAttribute('src') != '') {
                $output['script'][] = $tag->getAttribute('src');
            }
        }
        $output['script'] = array_unique($output['script']);

        $tags = $this->document->tags('img');

        foreach ($tags as $tag) {
            if ($tag->getAttribute('src') != '') {
                $output['img'][] = $tag->getAttribute('src');
            }
        }
        $output['img'] = array_unique($output['img']);

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Calculates page speed
     *
     * @return array<string, mixed>
     */
    public function pageSpeed(): array
    {
        return $this->output(number_format($this->page->fetchDuration, 4), __FUNCTION__);
    }

    /**
     * Checks if there is some plaintext email
     *
     * @return array<string, mixed>
     */
    public function plaintextEmail(): array
    {
        $page = Helpers::whitespace($this->document->text());
        $page = explode(' ', $page);

        $output = [];

        foreach ($page as $item) {
            $item = trim($item);

            if ($item != '' && strpos($item, '@') !== false) {
                if (!filter_var($item, FILTER_VALIDATE_EMAIL) === false) {
                    $output[] = $item;
                }
            }
        }

        $output = array_unique($output);

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Checks HTML page compression
     *
     * @return array<string, mixed>
     */
    public function pageCompression(): array
    {
        $compressed = gzcompress($this->page->body, 9);

        $output               = [];
        $output['actual']     = round(strlen($this->page->body) / 1024, 2);
        $output['possible']   = round(strlen((string) $compressed) / 1024, 2);
        $output['percentage'] = round((($output['possible'] * 100) / $output['actual']), 2);
        $output['difference'] = round($output['actual'] - $output['possible'], 2);

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Checks robots.txt
     *
     * @return array<string, mixed>
     */
    public function robotsFile(): array
    {
        $url    = "{$this->page->parsed->scheme}://{$this->page->parsed->host}/robots.txt";
        $output = $this->fetcher->status($url) === 200 ? $this->fetcher->body($url) : false;

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Headers that reveal the server stack.
     *
     * @return array<string, mixed>
     */
    public function serverSignature(): array
    {
        $output = [];
        $danger = ['server', 'powered'];

        foreach ($this->page->headers as $key => $values) {
            foreach ($danger as $needle) {
                if (str_contains(strtolower($key), $needle)) {
                    $output[$key] = $values[0] ?? '';
                }
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Social media accounts
     *
     * @return array<string, mixed>
     */
    public function socialMedia(): array
    {
        $socials = [
            'Facebook' => 'facebook.com',
            'Twitter'  => 'twitter.com',
            'LinkedIn' => 'linkedin.com',
            'YouTube'  => 'youtube.com',
            'GitHub'   => 'github.com',
        ];

        $output = [];
        $links  = Helpers::links($this->document, $this->page->parsed);

        foreach ($links as $link) {
            foreach ($socials as $key => $social) {
                if (strpos($link, $social) !== false) {
                    $output[$key][] = $link;
                }
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * SPF record
     *
     * @return array<string, mixed>
     */
    public function spfRecord(): array
    {
        $output = [];

        foreach ($this->dnsRecords() as $record) {
            if (strtoupper((string) $record['type']) == 'TXT' && strpos((string) $record['txt'], 'spf') !== false) {
                $output[] = $record['txt'];
            }
        }

        return $this->output($output, __FUNCTION__);
    }

    /**
     * Underscored links
     *
     * @return array<string, mixed>
     */
    public function underscoredLinks(): array
    {
        $output = [];
        $links  = Helpers::links($this->document, $this->page->parsed);

        foreach ($links as $link) {
            $_link = $link;
            $link  = (array) parse_url($link);

            if (count($link) && (!isset($link['host']) || $link['host'] == $this->page->parsed->host)) {
                if (strpos($_link, '_') !== false) {
                    $output[] = $_link;
                }
            }
        }

        return $this->output($output, __FUNCTION__);
    }
}
