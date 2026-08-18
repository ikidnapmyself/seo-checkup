<?php

namespace SEOCheckup;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use SEOCheckup\Exception\RequestFailedException;

final class Fetcher
{
    public const MAX_REDIRECTS = 5;

    public const USER_AGENT = 'seo-checkup/1.0 (+https://github.com/ikidnapmyself/seo-checkup)';

    private const CONNECT_TIMEOUT = 5.0;

    private const TIMEOUT = 15.0;

    private readonly ClientInterface $client;

    private readonly RequestFactoryInterface $requests;

    public function __construct(?ClientInterface $client = null, ?RequestFactoryInterface $requests = null)
    {
        $this->client = $client ?? new Client([
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout'         => self::TIMEOUT,
            'http_errors'     => false,
        ]);

        $this->requests = $requests ?? new HttpFactory();
    }

    /**
     * A 4xx or 5xx is data here, not an error. Only transport failures throw.
     *
     * Returns the response paired with the URL it was finally served from,
     * which is the last hop of the redirect chain rather than $url.
     *
     * @throws RequestFailedException on transport failure, or when the
     *                                redirect chain is still going after
     *                                MAX_REDIRECTS hops
     * @throws Exception\InvalidUrlException if $url is not a valid http(s)
     *                                       URL. Redirect targets never
     *                                       throw: UrlResolver returns an
     *                                       already-valid URL or null.
     */
    public function get(string $url): Fetched
    {
        $target    = $url;
        $redirects = 0;

        while (true) {
            // Validated up front so a malformed initial URL fails as
            // InvalidUrlException here, rather than reaching the request
            // factory's own URI parser. Later hops re-parse what
            // UrlResolver already validated and encoded, so they cannot fail.
            $currentUrl = Url::fromString($target);
            $target     = (string) $currentUrl;

            $request = $this->requests->createRequest('GET', $target)
                ->withHeader('User-Agent', self::USER_AGENT);

            try {
                $response = $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                throw new RequestFailedException(
                    sprintf('Request to "%s" failed: %s', $target, $e->getMessage()),
                    0,
                    $e
                );
            }

            // PSR-18 forbids transparent redirect following, so it happens here.
            $location = $response->getHeaderLine('Location');

            if (
                $location === ''
                || !in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)
            ) {
                return new Fetched($currentUrl, $response);
            }

            $next = UrlResolver::resolve($currentUrl, $location);

            // A Location the resolver cannot use makes this a terminal
            // response: there is nowhere to go, so it is the page.
            if ($next === null) {
                return new Fetched($currentUrl, $response);
            }

            // A followable redirect once the cap is spent is a chain that did
            // not terminate — a failed fetch, not a page. Returning the 3xx
            // stub here would let every check run against it as if it were
            // the document, and let brokenLinks() count a loop as passed.
            if ($redirects >= self::MAX_REDIRECTS) {
                throw new RequestFailedException(sprintf(
                    'Request to "%s" failed: more than %d redirects (last hop "%s" -> "%s").',
                    $url,
                    self::MAX_REDIRECTS,
                    $target,
                    $next
                ));
            }

            $target = $next;
            ++$redirects;
        }
    }

    /**
     * Status code of a probe request, or 0 when it could not be made.
     *
     * InvalidArgumentException is caught alongside the library's own
     * exceptions because Guzzle's StreamHandler -- the fallback when curl is
     * unavailable -- rethrows a bare one from GuzzleHttp\Psr7\Response when a
     * server answers with a status outside 100-599. It does not implement
     * ClientExceptionInterface, so get() cannot convert it, and without this
     * arm it would escape a method documented as total.
     */
    public function status(string $url): int
    {
        try {
            return $this->get($url)->response->getStatusCode();
        } catch (RequestFailedException | Exception\InvalidUrlException | \InvalidArgumentException) {
            return 0;
        }
    }

    /**
     * Body of a probe request, or an empty string when it could not be made.
     *
     * Catches the same set as status(), for the same reason.
     */
    public function body(string $url): string
    {
        try {
            return (string) $this->get($url)->response->getBody();
        } catch (RequestFailedException | Exception\InvalidUrlException | \InvalidArgumentException) {
            return '';
        }
    }
}
