# seo-checkup

A PHP toolbox that runs 29 SEO checks against a live URL and returns each result as a plain array.

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![CI](https://github.com/ikidnapmyself/seo-checkup/actions/workflows/ci.yml/badge.svg)](https://github.com/ikidnapmyself/seo-checkup/actions/workflows/ci.yml)

## Requirements

- PHP 8.3+
- ext-dom
- ext-mbstring
- ext-zlib

## Install

```
composer require ikidnapmyself/seo-checkup
```

## Usage

```php
$analyze = new SEOCheckup\Analyze('https://example.com');

print_r($analyze->metaTitle());
```

```
Array
(
    [url] => https://example.com
    [status] => 200
    [headers] => Array
        (
            [Content-Type] => Array
                (
                    [0] => text/html; charset=utf-8
                )

        )

    [service] => Meta Title
    [time] => 1786720888
    [data] => Example Page
)
```

Every example in this README is real output, captured through the library's own test suite against the bundled fixture (`tests/fixtures/complete.html`) rather than against the live site in the snippet — so `data` shows the fixture's title, not what `example.com` serves.

Every check returns the same envelope: `url` is the URL you asked for — the checks themselves reason about where the request finally landed, which differs whenever a redirect was followed — `status` is the fetched page's, `headers` are the response headers, `service` is a human-readable label derived from the method name, `time` is a Unix timestamp of when the check ran, and `data` is the check's own result — the only field that differs from check to check.

## Injecting a client

The constructor takes an optional [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client as its second argument:

```php
$analyze = new SEOCheckup\Analyze('https://example.com', $myPsr18Client);
```

Leave it out and the library builds its own Guzzle client with sane timeouts and a redirect cap. Pass one in and every request `Analyze` makes — the page itself, `robots.txt`, favicon probes, broken-link probes, external scripts for `googleAnalytics()` — goes through it instead. This is the seam that makes the library testable: the test suite injects a fake client and fixture HTML, and never touches the network. A third, optional argument accepts a `DnsLookup` implementation for the same reason — it backs the DNS lookup that `spfRecord()` uses.

## Checks

All 29 checks are camelCase methods on `Analyze`. Each returns the envelope above; the `data` column below describes what `data` holds.

| Check | `data` holds |
|---|---|
| `brokenLinks(int $limit = 25)` | `links` (all links found) and `scanned.errors` / `scanned.passed`, each keyed by `"HTTP {status}"`, for the first `$limit` links |
| `cache()` | `headers` (response headers whose name or value mentions "cache", as `Name: value`) and `html` (HTML comments mentioning "cache") |
| `canonicalTag()` | The resolved absolute href of `<link rel="canonical">`, or `''` |
| `characterSet()` | The charset parsed from the `Content-Type` header, or `''` |
| `codeContent()` | `page_size`, `code_size`, `content_size` (character counts, via `mb_strlen` — not bytes) and `percentage` — the ratio of visible text to page size — plus `content`: the page's full extracted visible text, which can be large |
| `deprecatedHtml()` | A map of deprecated tag name to count, for tags found on the page |
| `domainLength()` | Length of the host with its final label stripped. `example.co.uk` measures as `example.co` — correct registrable-domain extraction needs the Public Suffix List, which is a deferred dependency (see `DEFERRED.md`) |
| `favicon()` | The resolved favicon URL that responded 200, or `''` |
| `frameset()` | Counts of `<frameset>` and `<frame>` tags |
| `googleAnalytics()` | The first Universal Analytics `UA-XXXXX-X` ID found in inline or external scripts, or `''` |
| `header1()` | Text content of every `<h1>` on the page |
| `header2()` | Text content of every `<h2>` on the page |
| `https()` | `true`/`false` — whether the page was served over HTTPS |
| `imageAlt()` | `images` (every `<img>`'s `src`/`alt`) and `without_alt` (the `src`s missing an `alt`) |
| `inboundLinks()` | Links that stay on the same host |
| `inlineCss()` | Text content of every `<style>` block |
| `metaDescription()` | Content of `<meta name="description">`, or `''` |
| `metaTitle()` | Content of `<title>`, or `''` |
| `nofollowTag()` | `true`/`false` — whether any `<meta name="robots">` declares `nofollow` |
| `noindexTag()` | `true`/`false` — whether any `<meta name="robots">` declares `noindex` |
| `objectCount()` | Unique `css`, `script` and `img` asset URLs referenced by the page |
| `pageSpeed()` | Seconds spent fetching the document body, as a formatted string |
| `plaintextEmail()` | Email addresses found in the page's visible text |
| `pageCompression()` | `actual`/`possible` size in KB, `percentage` and `difference` if the body were gzipped |
| `robotsFile()` | The body of `/robots.txt` if it responded 200, otherwise `false` |
| `serverSignature()` | Response headers whose name or value contains "server" or "powered", keyed by header name |
| `socialMedia()` | Links grouped by social network (Facebook, Twitter, LinkedIn, YouTube, GitHub) |
| `spfRecord()` | TXT DNS records for the host that mention "spf" |
| `underscoredLinks()` | Same-host links containing an underscore |

## Errors

The constructor can throw:

- `SEOCheckup\Exception\InvalidUrlException` — the URL is malformed, has no host, uses a scheme other than `http`/`https`, or the host/path fails validation.
- `SEOCheckup\Exception\RequestFailedException` — the initial request could not be completed (DNS failure, connection refused, timeout, a redirect chain still going after `Fetcher::MAX_REDIRECTS` hops, and so on).

A URL that merely needs encoding is not invalid: spaces and other characters RFC 3986 does not allow in a path or query are percent-encoded on the way out, exactly as a browser does. URL-embedded credentials (`https://user:pw@staging.example.com/`) are sent as Basic auth. Unicode hostnames are converted to punycode when **ext-intl** is installed; without it the raw hostname is handed to the transport as-is.

Both extend `SEOCheckup\Exception\SeoCheckupException`. Captured from a real run:

```
SEOCheckup\Exception\InvalidUrlException: Only http and https URLs are supported, got "not a url".
```

Once construction succeeds, individual checks do not throw. A check that has nothing to report returns an empty value for its `data` — `''`, `false`, or an empty array, depending on the check — rather than raising an exception. `robotsFile()` and `favicon()`, for example, return `false` and `''` respectively when nothing is found, and network probes used inside checks (`brokenLinks()`, `favicon()`) treat an unreachable target as status `0` rather than propagating the failure.

## Security

Analyzing a page means fetching URLs that page controls. `brokenLinks()` probes the page's own links, `favicon()` probes its declared icon hrefs, and `googleAnalytics()` downloads its external scripts — all of them following redirects, and none of them restricting the host or IP that gets contacted. `brokenLinks()` then reports the status of every URL it probed.

The consequence is worth stating plainly: a page you analyze can make this library issue requests to hosts you did not choose, including addresses inside your own network (`http://127.0.0.1:8080/`, `http://169.254.169.254/`, an internal hostname), and `brokenLinks()` hands the resulting statuses back to the caller. That is enough to enumerate what is reachable from wherever the analysis runs. Treat the URL you pass to `Analyze` as untrusted input, and do not run the library against arbitrary user-supplied URLs from inside a network you care about.

The library ships no allowlist of its own, because the right policy depends on your network. The PSR-18 constructor argument is exactly where you install one: the client you inject sees every outgoing request, so a wrapping `ClientInterface` — or a Guzzle handler/middleware — can resolve the host, reject private and link-local ranges, pin an allowlist, or refuse redirects to hosts outside it, before the request goes out.

```php
$analyze = new SEOCheckup\Analyze('https://example.com', new MyHostAllowlistClient($guzzle));
```

## Upgrading from 0.1

- All methods are camelCase now (`BrokenLinks()` → `brokenLinks()`, `MetaTitle()` → `metaTitle()`, and so on for all 29). There are no deprecated PascalCase aliases.
- `PreRequirements` is gone. The library no longer builds a new HTTP client per request; a PSR-18 client is injected once, in the constructor.
- `canonicalTag()` (formerly `CanonicalTag()`) returns the resolved canonical href, e.g. `https://example.com/canonical`. It used to return the literal string `"canonical"`.
- `characterSet()` (formerly `CharacterSet()`) returns `''` when the `Content-Type` header has no charset, instead of raising a PHP error on `explode(';')[1]`.
- The package name is now `ikidnapmyself/seo-checkup`; the namespace is unchanged, `SEOCheckup\`.
- The constructor's second argument is now an optional PSR-18 `ClientInterface`, not a superglobal-touching base class.
