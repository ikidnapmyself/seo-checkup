<?php

namespace SEOCheckup;

final readonly class PageContext
{
    /**
     * $url and $parsed are deliberately not the same URL.
     *
     * $url is the string the caller asked for, kept verbatim so the check
     * envelope can answer "what did I request". $parsed is the URL the page
     * was actually served from — the last hop of the redirect chain — and is
     * what every check reasons about: the https verdict, the origin relative
     * links resolve against, the host domainLength() is measured on. The one
     * exception is DNS: spfRecord() queries the requested host, because SPF
     * belongs to the mail domain, not to a redirect target.
     *
     * They differ on any redirect, which includes the two most common
     * configurations on the web: apex -> www and http -> https.
     *
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public string $url,
        public Url $parsed,
        public int $status,
        public array $headers,
        public string $body,
        public float $fetchDuration,
    ) {
    }

    /**
     * @return list<string>
     */
    public function header(string $name): array
    {
        $name = strtolower($name);

        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $name) {
                return $values;
            }
        }

        return [];
    }

    public function headerLine(string $name): string
    {
        return $this->header($name)[0] ?? '';
    }
}
