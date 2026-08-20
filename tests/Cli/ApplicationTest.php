<?php

namespace SEOCheckup\Tests\Cli;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use SEOCheckup\Analyze;
use SEOCheckup\Cli\Application;
use SEOCheckup\Tests\Support\FakeDnsLookup;
use SEOCheckup\Tests\Support\FakeHttpClient;

final class ApplicationTest extends TestCase
{
    private string $originalCwd = '';

    private string $dir = '';

    /** Config auto-discovers ./seo-checkup.json, so run every test from an empty dir. */
    protected function setUp(): void
    {
        $cwd = getcwd();
        \assert($cwd !== false);
        $this->originalCwd = $cwd;
        $this->dir = sys_get_temp_dir() . '/seo-app-' . uniqid();
        mkdir($this->dir);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function app(?FakeHttpClient $http = null): Application
    {
        $http ??= new FakeHttpClient();

        return new Application(fn (string $url, int $timeout) => new Analyze($url, $http, new FakeDnsLookup()));
    }

    /** @return array{int, string, string} exit code, stdout, stderr */
    private function runApp(Application $app, string ...$args): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        \assert($out !== false && $err !== false);

        $code = $app->run(['seo-checkup', ...array_values($args)], $out, $err);

        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }

    public function testVersionPrintsAndExitsZero(): void
    {
        [$code, $out] = $this->runApp($this->app(), '--version');
        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/^seo-checkup \d+\.\d+\.\d+/', $out);
    }

    public function testHelpPrintsUsageAndExitsZero(): void
    {
        [$code, $out] = $this->runApp($this->app(), '--help');
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: seo-checkup <url>', $out);
        self::assertStringContainsString('--fail-on', $out);
    }

    public function testMissingUrlIsAUsageErrorExitTwo(): void
    {
        [$code, , $err] = $this->runApp($this->app());
        self::assertSame(2, $code);
        self::assertStringContainsString('<url> is required', $err);
        self::assertStringContainsString("Run 'seo-checkup --help' for usage.", $err);
    }

    public function testUnknownOptionIsAUsageErrorExitTwo(): void
    {
        [$code, , $err] = $this->runApp($this->app(), 'https://example.com', '--bogus');
        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown option: --bogus', $err);
    }

    public function testReportOnlyRunExitsZeroEvenWhenRulesFail(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head></head><body></body></html>');
        [$code, $out] = $this->runApp($this->app($http), 'https://example.com/', '--checks=meta', '--format=text');

        self::assertSame(0, $code);
        self::assertStringContainsString('FAIL  missing-title', $out);
        self::assertStringNotContainsString("\e[", $out, 'no colour on a non-TTY');
    }

    public function testFailOnRuleFailingExitsOne(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head></head><body></body></html>');
        [$code, $out] = $this->runApp($this->app($http), 'https://example.com/', '--checks=meta', '--fail-on=missing-title');

        self::assertSame(1, $code);
        self::assertStringContainsString('FAIL  missing-title [fail-on]', $out);
    }

    public function testFailOnRulePassingExitsZero(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head><title>T</title></head><body></body></html>');
        [$code] = $this->runApp($this->app($http), 'https://example.com/', '--checks=meta', '--fail-on=missing-title');

        self::assertSame(0, $code);
    }

    public function testPathsProduceOnePageEachAndOneFailingPageFailsTheRun(): void
    {
        $http = (new FakeHttpClient())
            ->route('https://example.com/', '<html><head><title>A</title></head></html>')
            ->route('https://example.com/about', '<html><head></head></html>');
        [$code, $out] = $this->runApp($this->app($http), 'https://example.com/', '--paths=/,/about', '--checks=metaTitle', '--fail-on=missing-title', '--format=json');

        self::assertSame(1, $code);
        $data = json_decode($out, true);
        self::assertIsArray($data);
        self::assertTrue($data['failed']);
        self::assertCount(2, $data['pages']);
        self::assertSame('https://example.com/', $data['pages'][0]['url']);
        self::assertSame('A', $data['pages'][0]['checks']['metaTitle']['data']);
        self::assertSame('https://example.com/about', $data['pages'][1]['url']);

        $byRule = fn (array $page): array => array_column($page['verdicts'], null, 'rule');
        self::assertSame('pass', $byRule($data['pages'][0])['missing-title']['result']);
        self::assertSame('fail', $byRule($data['pages'][1])['missing-title']['result']);
    }

    public function testMarkdownFormat(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head><title>T</title></head></html>');
        [$code, $out] = $this->runApp($this->app($http), 'https://example.com/', '--checks=metaTitle', '--format=md');

        self::assertSame(0, $code);
        self::assertStringStartsWith("# SEO checkup\n", $out);
        self::assertStringContainsString('| `missing-title` | ✅ pass |', $out);
    }

    public function testOutputWritesFileAndPrintsNothing(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html></html>');
        $file = $this->dir . '/report.json';
        [$code, $out] = $this->runApp($this->app($http), 'https://example.com/', '--checks=metaTitle', '--format=json', "--output={$file}");

        self::assertSame(0, $code);
        self::assertSame('', $out);
        self::assertStringContainsString('"pages"', (string) file_get_contents($file));
    }

    public function testUnwritableOutputIsAUsageErrorExitTwo(): void
    {
        $http = (new FakeHttpClient())->route('https://example.com/', '<html></html>');
        [$code, $out, $err] = $this->runApp($this->app($http), 'https://example.com/', '--checks=metaTitle', '--output=' . $this->dir . '/missing-dir/report.txt');

        self::assertSame(2, $code);
        self::assertSame('', $out);
        self::assertStringContainsString('Could not write', $err);
    }

    public function testConfigFileIsAutoDiscoveredAndFlagsWin(): void
    {
        file_put_contents('seo-checkup.json', json_encode(['url' => 'https://example.com/', 'checks' => ['metaTitle'], 'fail-on' => ['missing-title'], 'format' => 'json']));
        $http = (new FakeHttpClient())->route('https://example.com/', '<html><head></head></html>');

        [$code, $out] = $this->runApp($this->app($http));
        self::assertSame(1, $code, 'url, checks, fail-on and format from the file');
        self::assertStringStartsWith('{', $out);

        [$code] = $this->runApp($this->app($http), '--fail-on=none');
        self::assertSame(0, $code, 'flag overrides the file');
    }

    public function testUnfetchablePageExitsTwoWithMessage(): void
    {
        $http = new FakeHttpClient();
        $http->failWith = new class ('refused') extends \RuntimeException implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                return new Request('GET', 'https://example.com/');
            }
        };
        [$code, $out, $err] = $this->runApp($this->app($http), 'https://example.com/');

        self::assertSame(2, $code);
        self::assertSame('', $out);
        self::assertStringContainsString('Could not fetch https://example.com/', $err);
        self::assertStringNotContainsString("Run 'seo-checkup --help'", $err, 'not a usage problem');
    }

    public function testInvalidUrlExitsTwo(): void
    {
        [$code, , $err] = $this->runApp($this->app(), 'not a url');

        self::assertSame(2, $code);
        self::assertStringContainsString('Invalid URL', $err);
    }

    public function testUnknownCheckExitsTwo(): void
    {
        [$code, , $err] = $this->runApp($this->app(), 'https://example.com/', '--checks=bogus');

        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown check or group: bogus', $err);
    }

    public function testTimeoutIsPassedToTheFactory(): void
    {
        $seen = null;
        $http = (new FakeHttpClient())->route('https://example.com/', '<html></html>');
        $app  = new Application(function (string $url, int $timeout) use ($http, &$seen): Analyze {
            $seen = $timeout;

            return new Analyze($url, $http, new FakeDnsLookup());
        });

        [$code] = $this->runApp($app, 'https://example.com/', '--checks=metaTitle', '--timeout=3');
        self::assertSame(0, $code);
        self::assertSame(3, $seen);
    }
}
