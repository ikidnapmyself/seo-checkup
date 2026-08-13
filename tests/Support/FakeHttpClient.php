<?php

namespace SEOCheckup\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class FakeHttpClient implements ClientInterface
{
    /** @var array<string, ResponseInterface> */
    private array $routes = [];

    /** @var list<string> */
    public array $requested = [];

    public ?\Throwable $failWith = null;

    /**
     * @param array<string, string> $headers
     */
    public function route(string $url, string $body, int $status = 200, array $headers = []): self
    {
        $this->routes[$url] = new FakeResponse($status, $headers, $body);

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $url = (string) $request->getUri();
        $this->requested[] = $url;

        return $this->routes[$url] ?? new FakeResponse(404, [], '');
    }
}
