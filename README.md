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

Every check returns the same envelope: `url` and `status` are the fetched page's, `headers` are the response headers, `service` is a human-readable label derived from the method name, `time` is a Unix timestamp of when the check ran, and `data` is the check's own result — the only field that differs from check to check.

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
| `cache()` | `headers` (response header values mentioning "cache") and `html` (HTML comments mentioning "cache") |
| `canonicalTag()` | The resolved absolute href of `<link rel="canonical">`, or `''` |
| `characterSet()` | The charset parsed from the `Content-Type` header, or `''` |
| `codeContent()` | `page_size`, `code_size`, `content_size` (bytes) and `percentage` — the ratio of visible text to page size |
| `deprecatedHtml()` | A map of deprecated tag name to count, for tags found on the page |
| `domainLength()` | Length of the host with its final label stripped (see Upgrading, below) |
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
| `serverSignature()` | Response headers whose name contains "server" or "powered" |
| `socialMedia()` | Links grouped by social network (Facebook, Twitter, LinkedIn, YouTube, GitHub) |
| `spfRecord()` | TXT DNS records for the host that mention "spf" |
| `underscoredLinks()` | Same-host links containing an underscore |

## Errors

The constructor can throw:

- `SEOCheckup\Exception\InvalidUrlException` — the URL is malformed, has no host, uses a scheme other than `http`/`https`, or the host/path fails validation.
- `SEOCheckup\Exception\RequestFailedException` — the initial request could not be completed (DNS failure, connection refused, timeout, and so on).

Both extend `SEOCheckup\Exception\SeoCheckupException`. Captured from a real run:

```
SEOCheckup\Exception\InvalidUrlException: Only http and https URLs are supported, got "not a url".
```

Once construction succeeds, individual checks do not throw. A check that has nothing to report returns an empty value for its `data` — `''`, `false`, or an empty array, depending on the check — rather than raising an exception. `robotsFile()` and `favicon()`, for example, return `false` and `''` respectively when nothing is found, and network probes used inside checks (`brokenLinks()`, `favicon()`) treat an unreachable target as status `0` rather than propagating the failure.

## Upgrading from 0.1

- All methods are camelCase now (`BrokenLinks()` → `brokenLinks()`, `MetaTitle()` → `metaTitle()`, and so on for all 29). There are no deprecated PascalCase aliases.
- `PreRequirements` is gone. The library no longer builds a new HTTP client per request; a PSR-18 client is injected once, in the constructor.
- `canonicalTag()` (formerly `CanonicalTag()`) returns the resolved canonical href, e.g. `https://example.com/canonical`. It used to return the literal string `"canonical"`.
- `characterSet()` (formerly `CharacterSet()`) returns `''` when the `Content-Type` header has no charset, instead of raising a PHP error on `explode(';')[1]`.
- The package name is now `ikidnapmyself/seo-checkup`; the namespace is unchanged, `SEOCheckup\`.
- The constructor's second argument is now an optional PSR-18 `ClientInterface`, not a superglobal-touching base class.
