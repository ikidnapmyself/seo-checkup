### SEO Checkup Toolbox

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/frnxstd/seo-checkup/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/frnxstd/seo-checkup/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/frnxstd/seo-checkup/badges/build.png?b=master)](https://scrutinizer-ci.com/g/frnxstd/seo-checkup/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/frnxstd/seo-checkup/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

It's a basic package written in PHP for good. As most people know that, SEO services have been expensive for low budget 
developers. I made a simple package to let you check yourself with this package. It became quite ugly while I was coding,
but I am going to make a free service in the future and I will use this package. During the development process of the service, 
I will update this class as much as I need.

### Installation

You will need, `Composer`. If you already have it, run the code, shown below
```
composer require frnxstd/seo-checkup
```

Enjoy.

### Contribution

Tests and re-design for the class is required. 

Any contribution in this case, more than welcome.

### List of Services The Package Serves

- Broken Links  `BrokenLinks()`
- Cache `Cache()`
- Canonica lTag `CanonicalTag()`
- Character Set `CharacterSet()`
- Code Content Ratio    `CodeContent()`
- Deprecated HTML Tags  `DeprecatedHTML()`
- Domain Length `DomainLength()`
- Favicon   `Favicon()`
- Frameset  `Frameset()`
- Google Analytics  `GoogleAnalytics()`
- Header1   `Header1()`
- Header2   `Header2()`
- Https `Https()`
- Image Alt `ImageAlt()`
- Inbound Links `InboundLinks()`
- Inline Css    `InlineCss()`
- Meta Description  `MetaDescription()`
- Meta Title    `MetaTitle()`
- No-follow Tag `NofollowTag()`
- No-index Tag  `NoindexTag()`
- Object Counter    `ObjectCount()`
- Page peed `PageSpeed()`
- Plaintext Email   `PlaintextEmail()`
- Page Compression  `PageCompression()`
- Robots.txt File   `RobotsFile()`
- Server Signature  `ServerSignature()`
- Social Media Links    `SocialMedia()`
- Spf Record    `SpfRecord()`
- Underscored Links `UnderscoredLinks()`

### How to use?

You can use any function listed above. An example usage is here:
```
require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

use SEOCheckup\Analyze;

$analyze = new Analyze('http://domain.com');

var_dump($analyze->NoindexTag());
```