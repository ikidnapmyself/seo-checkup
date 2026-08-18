<?php

namespace SEOCheckup\Tests\Support;

use GuzzleHttp\Psr7\MessageTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-7 response for tests.
 *
 * GuzzleHttp\Psr7\Response rejects status codes outside 100-599, but
 * LinkedIn's real-world bot-block response is the non-standard 999 — a
 * status Fetcher::status() must be able to observe in fakes.
 */
final class FakeResponse implements ResponseInterface
{
    use MessageTrait;

    private int $statusCode;

    private string $reasonPhrase;

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(int $status = 200, array $headers = [], string $body = '', string $reasonPhrase = '')
    {
        $this->statusCode   = $status;
        $this->reasonPhrase = $reasonPhrase;
        $this->setHeaders($headers);
        $this->stream = Utils::streamFor($body);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $new               = clone $this;
        $new->statusCode   = $code;
        $new->reasonPhrase = $reasonPhrase;

        return $new;
    }
}
