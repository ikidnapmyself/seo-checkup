# seo-checkup

A PHP toolbox that runs 29 SEO checks against a live URL and returns each result as a plain array — as a library, a `seo-checkup` command, or a GitHub Action.

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![CI](https://github.com/ikidnapmyself/seo-checkup/actions/workflows/ci.yml/badge.svg)](https://github.com/ikidnapmyself/seo-checkup/actions/workflows/ci.yml)
[![Action smoke](https://github.com/ikidnapmyself/seo-checkup/actions/workflows/action-smoke.yml/badge.svg)](https://github.com/ikidnapmyself/seo-checkup/actions/workflows/action-smoke.yml)
[![GitHub Marketplace](https://img.shields.io/badge/marketplace-seo--checkup-2088FF?logo=githubactions&logoColor=white)](https://github.com/marketplace/actions/seo-checkup)

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

Every example in this README is real output. The library examples are captured through the library's own test suite against the bundled fixture (`tests/fixtures/complete.html`) rather than against the live site in the snippet — so `data` shows the fixture's title, not what `example.com` serves. Each example says so when it was captured differently (the CLI example below was run against the live example.com).

Every check returns the same envelope: `url` is the URL you asked for — the checks themselves reason about where the request finally landed, which differs whenever a redirect was followed — `status` is the fetched page's, `headers` are the response headers, `service` is a human-readable label derived from the method name, `time` is a Unix timestamp of when the check ran, and `data` is the check's own result — the only field that differs from check to check.

## Injecting a client

The constructor takes an optional [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client as its second argument:

```php
$analyze = new SEOCheckup\Analyze('https://example.com', $myPsr18Client);
```

Leave it out and the library builds its own Guzzle client with sane timeouts and a redirect cap. Pass one in and every request `Analyze` makes — the page itself, `robots.txt`, favicon probes, broken-link probes, external scripts for `googleAnalytics()` — goes through it instead. This is the seam that makes the library testable: the test suite injects a fake client and fixture HTML, and never touches the network. A third, optional argument accepts a `DnsLookup` implementation for the same reason — it backs the DNS lookup that `spfRecord()` uses.

## Command line

The package ships a `seo-checkup` command that runs the checks above against one or more pages, judges the results against a small rule catalogue, and exits with a code CI can act on.

Install it per project:

```
composer require --dev ikidnapmyself/seo-checkup
vendor/bin/seo-checkup --help
```

or globally, which puts `seo-checkup` on your PATH (run `composer global config bin-dir --absolute` to find the directory to add to PATH if it is not there already):

```
composer global require ikidnapmyself/seo-checkup
seo-checkup --help
```

```
Usage: seo-checkup <url> [options]

  --paths=/,/about           Extra paths resolved against <url>; default: just <url>
  --checks=meta,links,https  Groups and/or check names; default: all (network group
                             skipped for localhost / *.local / *.test hosts)
  --fail-on=broken-links,…   Rules or presets (all, recommended, none) that fail the run
  --format=text|md|json      Output format; default: text
  --output=FILE              Write the report to FILE instead of stdout
  --config=FILE              Config file; default: ./seo-checkup.json if present
  --timeout=N                Seconds per request; default: 15
  --help                     Show this help
  --version                  Show the version

Exit codes: 0 all fail-on rules passed · 1 a fail-on rule failed · 2 usage / fetch error
```

### Check groups

`--checks` takes group names, individual check (method) names, or a mix; output order is always catalogue order, however the flags were typed.

| Group | Checks |
|---|---|
| `meta` | `metaTitle`, `metaDescription`, `canonicalTag`, `noindexTag`, `nofollowTag`, `robotsFile` |
| `links` | `brokenLinks`, `inboundLinks`, `underscoredLinks`, `socialMedia`, `plaintextEmail` |
| `content` | `header1`, `header2`, `imageAlt`, `codeContent`, `deprecatedHtml`, `frameset`, `inlineCss`, `objectCount`, `favicon`, `googleAnalytics` |
| `headers` | `cache`, `characterSet`, `serverSignature`, `pageCompression` |
| `network` | `https`, `spfRecord`, `domainLength` |
| `performance` | `pageSpeed` |

Without `--checks`, every group runs — except that the `network` group is skipped when the host is local (`localhost` and `*.localhost`, `127.*` loopback addresses, `[::1]`, and the `*.local` / `*.test` dev TLDs): HTTPS, SPF and domain length say nothing about a dev server. An explicit `--checks` is honoured exactly as given, local host or not.

### Rules and `--fail-on`

The checks report data; the rules judge it. Every run evaluates all 13 rules and prints a verdict per rule, but only the rules named in `--fail-on` can fail the run (exit code 1). A rule whose check was not selected is reported as `skip` and never fails the run, so a narrow `--checks` cannot trip `--fail-on` by accident.

| Rule | Check | Fails when |
|---|---|---|
| `broken-links` | `brokenLinks` | any scanned link probes as an error status |
| `missing-title` | `metaTitle` | the page has no (or an empty) `<title>` |
| `missing-description` | `metaDescription` | no meta description |
| `missing-canonical` | `canonicalTag` | no canonical tag |
| `noindex` | `noindexTag` | the page declares `noindex` |
| `not-https` | `https` | the page was not served over HTTPS |
| `missing-h1` | `header1` | no `<h1>` on the page |
| `multiple-h1` | `header1` | more than one `<h1>` |
| `images-without-alt` | `imageAlt` | any `<img>` is missing an `alt` |
| `plaintext-email` | `plaintextEmail` | an email address appears in the page's visible text |
| `underscored-links` | `underscoredLinks` | a same-host link contains an underscore |
| `deprecated-html` | `deprecatedHtml` | any deprecated HTML tag is found |
| `no-robots-txt` | `robotsFile` | `/robots.txt` is absent |

`--fail-on` also takes three presets: `recommended` expands to `broken-links`, `missing-title`, `missing-description`, `missing-canonical` and `not-https`; `all` expands to every rule; `none` expands to nothing (report only — the default when `--fail-on` is not given at all).

### Output

- `text` (default) — the report below; coloured when stdout is a TTY.
- `md` — GitHub-flavoured Markdown, one verdict table per page with the raw check data collapsed; made for CI job summaries.
- `json` — machine-readable: `{failed, pages: [{url, status, verdicts: [{rule, result, message, failsRun}], checks: {method: envelope}}]}` where each `envelope` is the unmodified check envelope from the library.

`--output=FILE` writes the report to a file instead of stdout.

### Config file

The CLI reads `seo-checkup.json` from the current directory when it exists, or the file named by `--config`. Precedence is flags > file > defaults. Recognised keys: `url`, `paths`, `checks`, `fail-on`, `format`, `timeout`, `overrides`.

```json
{
  "url": "https://example.com",
  "paths": ["/", "/about", "/blog/hello-world"],
  "checks": ["meta", "content"],
  "fail-on": ["recommended"],
  "format": "text",
  "timeout": 30,
  "overrides": {
    "/blog/*": { "checks": ["meta", "links"], "fail-on": ["broken-links"] }
  }
}
```

`overrides` maps a path glob to per-page settings: each glob is matched with `fnmatch` against the resolved page URL's path (no query), and `*` crosses `/` — `/blog/*` matches `/blog/2026/01/post` too. The file is validated eagerly: an unknown rule or check name, an unknown `format`, or a non-positive `timeout` is an error (exit 2) before anything is fetched.

### Example

A real run, captured on 2026-08-20 against the live example.com; the exit code was 1 because `recommended` includes `missing-description` and `missing-canonical`, and example.com has neither:

```
$ seo-checkup https://example.com --checks=meta --fail-on=recommended

https://example.com/ (HTTP 200)
  SKIP  broken-links: check not run
  PASS  missing-title [fail-on]: title present
  FAIL  missing-description [fail-on]: no meta description
  FAIL  missing-canonical [fail-on]: no canonical tag
  PASS  noindex: indexable
  SKIP  not-https: check not run
  SKIP  missing-h1: check not run
  SKIP  multiple-h1: check not run
  SKIP  images-without-alt: check not run
  SKIP  plaintext-email: check not run
  SKIP  underscored-links: check not run
  SKIP  deprecated-html: check not run
  FAIL  no-robots-txt: no /robots.txt

  Meta Title
    Example Domain
  Meta Description
    ""
  Canonical Tag
    ""
  Noindex Tag
    false
  Nofollow Tag
    false
  Robots File
    false

1 page checked, 1 failed.
```

## GitHub Action

The repository doubles as a composite GitHub Action: on pull requests it checks the branch — either a preview URL your deploy produced, or a dev server the action starts in the runner — and on `master` it checks production. It prints the report in the step log, writes the verdict tables to the job summary, annotates every failed rule (`error` when it fails the step, `warning` otherwise), uploads the JSON report as an artifact, and fails the step on exactly the rules you choose.

### Inputs

| Input | Default | Description |
|---|---|---|
| `url` | — | Page to check (a deployed site or preview URL). Required unless `serve` is set. |
| `serve` | — | Shell command that starts a local server in the runner (e.g. `npm run dev -- --port 4321`). The page at `serve-url` is checked. |
| `serve-url` | `http://localhost:3000` | URL where `serve` listens; the action waits for it before checking. |
| `serve-timeout` | `60` | Seconds to wait for `serve-url` to answer. |
| `paths` | — | Comma-separated paths resolved against the URL (e.g. `/,/about,/blog`). Default: just the URL. |
| `checks` | — | Comma-separated groups and/or check names (meta, links, content, headers, network, performance, or e.g. metaTitle). Default: all (network checks skipped for local hosts). |
| `fail-on` | — | Comma-separated rules or presets (all, recommended, none) whose failure fails the step. Default: none (report only). |
| `config` | — | Path to a seo-checkup.json. Default: ./seo-checkup.json when present. |
| `php-version` | `8.3` | PHP version for setup-php. |
| `artifact` | `true` | Upload the JSON report as a workflow artifact (see `artifact-name`). |
| `artifact-name` | `seo-checkup-report` | Name of the uploaded report artifact. Must be unique per workflow run — set it when the action runs more than once (matrix, several sites). |

### Outputs

| Output | Description |
|---|---|
| `failed` | `true` when a fail-on rule failed on any page, else `false`. |
| `report` | Absolute path to the JSON report. |

### Workflow

```yaml
name: SEO
on:
  pull_request:
  push:
    branches: [master]
jobs:
  seo:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      # Pull requests: run the branch locally and check it
      - uses: ikidnapmyself/seo-checkup@v1
        if: github.event_name == 'pull_request'
        with:
          serve: npm ci && npm run dev -- --port 4321
          serve-url: http://localhost:4321
      # master: check production
      - uses: ikidnapmyself/seo-checkup@v1
        if: github.ref == 'refs/heads/master'
        with:
          url: https://example.com
```

`fail-on`, `paths` and `checks` normally live in the repository's own `seo-checkup.json`, which the CLI auto-discovers after checkout — that keeps both steps this short, and the `url` input overrides the file's `url`, so the same file serves the PR step and the production step.

If your deploy already produces a preview URL, check that instead of serving locally (note that `actions/deploy-pages` itself needs `pages: write` and `id-token: write` — the no-extra-permissions statement below is about the seo-checkup action only):

```yaml
      - id: deployment
        uses: actions/deploy-pages@v4
      - uses: ikidnapmyself/seo-checkup@v1
        with:
          url: ${{ steps.deployment.outputs.page_url }}
```

### Notes

- The action needs no permissions beyond the default `contents: read`.
- It installs PHP via setup-php (the `php-version` input) and the package via Composer: running the action at a release tag `vX.Y.Z` installs that package version, `vX` the latest release in that major, and a branch or SHA reference runs the action's own checkout. Use `@v1` for the latest 1.x, or pin `@v1.1.0`.
- `artifact-name` must be unique per workflow run if the action runs more than once (matrix builds, several sites).
- Runs against a local host (the `serve` mode's default) skip the `network` check group by default, like the CLI.
- The step log and the job summary are produced by extra CLI runs, so each page is fetched three times — a known limitation, see DEFERRED.md.
- `$GITHUB_STEP_SUMMARY` caps at 1 MiB; on big multi-page runs, narrow `--checks` (the raw check data dominates the summary's size).

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
| `spfRecord()` | TXT DNS records mentioning "spf" for the host you asked about (the requested host, not a redirect target — SPF belongs to the mail domain, normally the apex) |
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

These caveats apply equally to the CLI and the GitHub Action, which fetch the same page-controlled URLs from wherever they run.

## Upgrading from 0.1

- All methods are camelCase now (`BrokenLinks()` → `brokenLinks()`, `MetaTitle()` → `metaTitle()`, and so on for all 29). There are no deprecated PascalCase aliases.
- `PreRequirements` is gone. The library no longer builds a new HTTP client per request; a PSR-18 client is injected once, in the constructor.
- `canonicalTag()` (formerly `CanonicalTag()`) returns the resolved canonical href, e.g. `https://example.com/canonical`. It used to return the literal string `"canonical"`.
- `characterSet()` (formerly `CharacterSet()`) returns `''` when the `Content-Type` header has no charset, instead of raising a PHP error on `explode(';')[1]`.
- The package name is now `ikidnapmyself/seo-checkup`; the namespace is unchanged, `SEOCheckup\`.
- The constructor's second argument is now an optional PSR-18 `ClientInterface`, not a superglobal-touching base class.
