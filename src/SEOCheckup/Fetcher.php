<?php

namespace SEOCheckup;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
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
     * @throws RequestFailedException
     */
    public function get(string $url): ResponseInterface
    {
        $target    = $url;
        $redirects = 0;

        while (true) {
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
                $redirects >= self::MAX_REDIRECTS
                || $location === ''
                || !in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)
            ) {
                return $response;
            }

            $next = UrlResolver::resolve(Url::fromString($target), $location);

            if ($next === null) {
                return $response;
            }

            $target = $next;
            ++$redirects;
        }
    }

    /**
     * Status code of a probe request, or 0 when it could not be made.
     */
    public function status(string $url): int
    {
        try {
            return $this->get($url)->getStatusCode();
        } catch (RequestFailedException | Exception\InvalidUrlException) {
            return 0;
        }
    }

    /**
     * Body of a probe request, or an empty string when it could not be made.
     */
    public function body(string $url): string
    {
        try {
            return (string) $this->get($url)->getBody();
        } catch (RequestFailedException | Exception\InvalidUrlException) {
            return '';
        }
    }
}
