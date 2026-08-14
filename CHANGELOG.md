# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - 2026-08-14

The first tagged release. `v0.1-beta` (February 2018) was the only prior tag; there was never a working, tested 1.0 before this.

### Added

- A PSR-18 `ClientInterface` as an optional second constructor argument (`new Analyze($url, $http)`), and an optional third `DnsLookup` argument — the seam that makes the library testable without touching the network.
- Typed exceptions: `SEOCheckup\Exception\SeoCheckupException` (base), `InvalidUrlException`, `RequestFailedException`, both thrown only from the constructor.
- A hermetic PHPUnit test suite covering all 29 checks, each with a happy-path and an empty/malformed-input case.
- PHPStan (level 8) and PHP-CS-Fixer (PSR-12) as CI gates, run on PHP 8.3, 8.4 and 8.5.
- `Analyze::BROKEN_LINKS_LIMIT`, `Analyze::EXTERNAL_SCRIPT_LIMIT`, `Analyze::FAVICON_CANDIDATE_LIMIT` and `Fetcher::MAX_REDIRECTS` as named, tunable bounds. `brokenLinks(int $limit = self::BROKEN_LINKS_LIMIT)` exposes its cap as a parameter.
- `DEFERRED.md`, a register of work considered and knowingly not built in this release.

### Changed

- **Package renamed** from `frnxstd/seo-checkup` to `ikidnapmyself/seo-checkup`. The old name is marked abandoned via a `replace` pointer. The namespace is unchanged: `SEOCheckup\`.
- **All 29 public methods renamed PascalCase → camelCase** (`BrokenLinks()` → `brokenLinks()`, `MetaTitle()` → `metaTitle()`, and so on), with no deprecated aliases.
- **PHP floor raised to 8.3.** Not 8.4, deliberately — PHP 8.4 ships a real HTML5 parser (`Dom\HTMLDocument`) that would change nearly every check's output at once; see `DEFERRED.md`.
- `PreRequirements` is gone. HTTP requests now go through an injected PSR-18 client (built once) instead of a base class that constructed a new Guzzle client on every call.
- DNS lookups moved behind a lazy accessor, fired only when `spfRecord()` is actually called, instead of unconditionally in the constructor. This also fixes `pageSpeed()`, which previously included DNS resolution time as page speed.
- The `service` label derivation was rewritten so camelCase method names produce `"Broken Links"` (capitalized, no leading space) instead of a malformed label.
- Internal `$this->data` array replaced by a typed, readonly `PageContext` value object. The `Output()`/`output()` envelope shape is unchanged.
- The duplicated relative-URL resolution logic in `Helpers::Links()` and `Analyze::InboundLinks()` (which had drifted apart) was unified into a single `UrlResolver`.
- Failure handling: connect and total timeouts, a real `User-Agent`, and a redirect cap (`Fetcher::MAX_REDIRECTS`) were added to outgoing requests.

### Fixed

Ten confirmed defects, each closed in its own commit with a failing test written first. Items 3, 6, 7, 8 and 10 change check output — the old values were wrong, not merely different:

1. `favicon()` read `$_GET['value']`, a web-request superglobal, inside a library — an undefined-key warning and a nonsense URL on the fallback path. The branch is deleted; the icon href is resolved against the page URL.
2. `googleAnalytics()` indexed `$ua_id[0][0]` on no regex match, an undefined-key error. It now returns `''` when nothing matches, and bounds the number of external scripts it fetches (`Analyze::EXTERNAL_SCRIPT_LIMIT`).
3. `characterSet()` chained `explode(';')[1]` then `explode('=')[1]`, fatal on a bare `Content-Type: text/html`, and matched the header name case-sensitively so `content-type` was missed entirely. It now does a case-insensitive header lookup with regex extraction and returns `''` when no charset is present.
4. `codeContent()` and `pageCompression()` divided by zero on an empty body. Both now guard and return zeroed metrics.
5. `inboundLinks()` used `strpos($path,'/') === false`, a drifted copy of the Helpers resolver that mangled relative paths. It now calls the shared `UrlResolver`.
6. `Helpers::links()` let `mailto:` and `tel:` links through the filter, emitting nonsense like `mailto://example.com`, and ignored `<base href>`. Non-HTTP schemes are now skipped and `<base href>` is honored.
7. `nofollowTag()` and `noindexTag()` used `in_array('nofollow', $output)` against whole content strings, missing the normal `content="index, nofollow"` form almost always. Content is now split on commas, trimmed, and compared case-insensitively.
8. `canonicalTag()` returned the literal string `"canonical"` instead of the tag's href. It now returns the resolved canonical URL, or `''`.
9. `domainLength()` threw an undefined-index error when the URL had no host. The crash is fixed; single-label stripping is intentionally kept (`example.co.uk` still measures as `example.co` — see `DEFERRED.md` for the Public Suffix List item).
10. `brokenLinks()` capped at 24 links via an off-by-one `$i >= 25` loop, undocumented, and compared HTTP status classes with `substr($status,0,1) > 3`, a string comparison. It now has a named limit constant exposed as a parameter, and an integer status-class comparison. This also fixed a latent bug: status `0` (an unreachable probe) was previously counted as passed.

The final whole-branch review before tagging found three more, closed the same way:

11. `Fetcher::get()` followed redirects but discarded the URL the chain ended on, so every check reasoned about the requested URL rather than the page actually served. On the two most common configurations on the web — apex → www and http → https — `https()` returned the wrong verdict, and `Helpers::links()`, `inboundLinks()`, `underscoredLinks()`, `canonicalTag()`, `favicon()` and `domainLength()` all resolved against an origin the page was never served from, after which `brokenLinks()` probed URLs that do not exist and reported them broken. `get()` now returns a readonly `Fetched` value object carrying the final `Url` alongside the response. The envelope's `url` field still reports the URL the caller asked for.
12. `UrlResolver::resolve()` appended a query-only href to the base's *directory* instead of leaving the base path unchanged, contrary to RFC 3986 §5.3: on `https://example.com/blog/post.html`, `<a href="?page=2">` resolved to `https://example.com/blog/?page=2` where every browser produces `https://example.com/blog/post.html?page=2`. That is standard pagination markup, so the invented URL reached `links`, `inboundLinks`, `socialMedia`, `underscoredLinks` and `brokenLinks` (as false "broken" reports).
13. `composer.json` declared no extension requirements although `src/` hard-depends on **ext-dom**, **ext-mbstring** and **ext-zlib**; a machine without ext-zlib installed cleanly and fatalled at runtime in `pageCompression()`. All three are now in `require`.

Also from that review: `Fetcher::status()` and `body()` now honour their documented never-throws contract on Guzzle's curl-less `StreamHandler` path, which rethrows a bare `InvalidArgumentException` that no PSR-18 interface covers; and `brokenLinks()` clamps a non-positive `$limit` to zero instead of letting `array_slice()` read it as "all but the last N".

### Removed

- `PreRequirements` base class.
- `Helpers::attributes()`, which had no caller in `src/` — unused public surface, dropped rather than shipped in a first release. The 29 checks on `Analyze` are unaffected.
- The old PascalCase method names. There are no aliases; anything calling `Analyze::BrokenLinks()` etc. must switch to the camelCase name.
