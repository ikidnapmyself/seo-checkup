<?php

namespace SEOCheckup\Tests\Cli;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use SEOCheckup\Analyze;
use SEOCheckup\Cli\PageSettings;
use SEOCheckup\Cli\Runner;
use SEOCheckup\Cli\Verdict;
use SEOCheckup\Exception\RequestFailedException;
use SEOCheckup\Tests\Support\FakeDnsLookup;
use SEOCheckup\Tests\Support\FakeHttpClient;

final class RunnerTest extends TestCase
{
    private function runner(FakeHttpClient $http): Runner
    {
        return new Runner(fn (string $url) => new Analyze($url, $http, new FakeDnsLookup()));
    }

    public function testRunsOnlySelectedChecksAndEvaluatesEveryRule(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head><title>T</title></head><body><h1>A</h1></body></html>');
        $result = $this->runner($http)->run('https://example.com/', new PageSettings(['metaTitle', 'header1'], ['missing-title']));

        self::assertSame('https://example.com/', $result->url);
        self::assertSame(200, $result->status);
        self::assertSame(['metaTitle', 'header1'], array_keys($result->checks));
        self::assertSame('T', $result->checks['metaTitle']['data']);

        self::assertCount(13, $result->verdicts);
        self::assertSame(Verdict::PASS, $result->verdicts['missing-title']->result);
        self::assertSame(Verdict::PASS, $result->verdicts['missing-h1']->result);
        self::assertSame(Verdict::SKIP, $result->verdicts['broken-links']->result, 'check not selected');
        self::assertTrue($result->verdicts['missing-title']->failsRun);
        self::assertFalse($result->verdicts['missing-h1']->failsRun);
        self::assertFalse($result->failed);
    }

    public function testFailedWhenAFailOnRuleFails(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head></head><body></body></html>');
        $result = $this->runner($http)->run('https://example.com/', new PageSettings(['metaTitle'], ['missing-title']));

        self::assertSame(Verdict::FAIL, $result->verdicts['missing-title']->result);
        self::assertTrue($result->failed);
    }

    public function testFailingRuleNotInFailOnDoesNotFailTheRun(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head></head><body></body></html>');
        $result = $this->runner($http)->run('https://example.com/', new PageSettings(['metaTitle'], []));

        self::assertSame(Verdict::FAIL, $result->verdicts['missing-title']->result);
        self::assertFalse($result->failed);
    }

    public function testFailOnRuleWhoseCheckWasNotSelectedIsSkippedNotFailed(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head></head><body></body></html>');
        $result = $this->runner($http)->run('https://example.com/', new PageSettings(['header1'], ['missing-title']));

        self::assertSame(Verdict::SKIP, $result->verdicts['missing-title']->result);
        self::assertTrue($result->verdicts['missing-title']->failsRun);
        self::assertFalse($result->failed);
    }

    public function testNullChecksRunsTheDefaultSet(): void
    {
        $http = (new FakeHttpClient())->route('http://localhost:3000/', '<html><head><title>T</title></head></html>');
        $result = $this->runner($http)->run('http://localhost:3000/', new PageSettings(null, []));

        self::assertCount(26, $result->checks, 'local host: network group skipped');
        self::assertSame(Verdict::SKIP, $result->verdicts['not-https']->result);
    }

    public function testStatusComesFromTheFetchedPage(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/missing', '<html></html>', 404);
        $result = $this->runner($http)->run('https://example.com/missing', new PageSettings(['metaTitle'], []));

        self::assertSame(404, $result->status);
    }

    public function testFetchFailurePropagates(): void
    {
        $http = new FakeHttpClient();
        $http->failWith = new class ('boom') extends \RuntimeException implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                return new Request('GET', 'https://example.com/');
            }
        };

        $this->expectException(RequestFailedException::class);
        $this->runner($http)->run('https://example.com/', new PageSettings(null, []));
    }
}
