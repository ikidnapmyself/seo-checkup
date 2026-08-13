<?php

namespace SEOCheckup\Tests\Checks;

use PHPUnit\Framework\TestCase;
use SEOCheckup\Tests\Support\AnalyzeFactory;

final class HeaderChecksTest extends TestCase
{
    public function testCharacterSetReadsTheCharsetParameter(): void
    {
        $analyze = AnalyzeFactory::make('<html></html>', ['Content-Type' => 'text/html; charset=utf-8']);

        self::assertSame('utf-8', $analyze->characterSet()['data']);
    }

    /**
     * Spec defect 3: header keys were compared case-sensitively.
     */
    public function testCharacterSetMatchesLowercaseHeaderName(): void
    {
        $analyze = AnalyzeFactory::make('<html></html>', ['content-type' => 'text/html; charset=ISO-8859-1']);

        self::assertSame('ISO-8859-1', $analyze->characterSet()['data']);
    }

    /**
     * Spec defect 3: this used to be an undefined-offset error.
     */
    public function testCharacterSetIsEmptyForABareContentType(): void
    {
        $analyze = AnalyzeFactory::make('<html></html>', ['Content-Type' => 'text/html']);

        self::assertSame('', $analyze->characterSet()['data']);
    }

    public function testCharacterSetIsEmptyWhenTheHeaderIsAbsent(): void
    {
        $analyze = AnalyzeFactory::make('<html></html>', []);

        self::assertSame('', $analyze->characterSet()['data']);
    }

    public function testCharacterSetStripsQuotes(): void
    {
        $analyze = AnalyzeFactory::make('<html></html>', ['Content-Type' => 'text/html; charset="utf-8"']);

        self::assertSame('utf-8', $analyze->characterSet()['data']);
    }

    public function testServerSignatureReportsRevealingHeaders(): void
    {
        $analyze = AnalyzeFactory::make('<html></html>', [
            'Server'       => 'nginx/1.24.0',
            'X-Powered-By' => 'PHP/8.3.0',
            'Content-Type' => 'text/html',
        ]);

        $data = $analyze->serverSignature()['data'];

        self::assertSame('nginx/1.24.0', $data['Server']);
        self::assertSame('PHP/8.3.0', $data['X-Powered-By']);
        self::assertArrayNotHasKey('Content-Type', $data);
    }

    public function testCacheReportsCacheHeadersAndHtmlComments(): void
    {
        $analyze = AnalyzeFactory::make(
            '<html><body><!-- cached at 12:00 --><p>x</p></body></html>',
            ['Cache-Control' => 'max-age=600', 'Content-Type' => 'text/html']
        );

        $data = $analyze->cache()['data'];

        self::assertSame(['max-age=600'], $data['headers']);
        self::assertCount(1, $data['html']);
    }
}
