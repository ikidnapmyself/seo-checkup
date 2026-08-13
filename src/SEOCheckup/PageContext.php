<?php

namespace SEOCheckup;

final readonly class PageContext
{
    /**
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
