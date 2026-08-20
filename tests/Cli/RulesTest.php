<?php

namespace SEOCheckup\Tests\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SEOCheckup\Cli\RuleCatalogue;
use SEOCheckup\Cli\UsageException;
use SEOCheckup\Cli\Verdict;

final class RulesTest extends TestCase
{
    /**
     * rule name, check method, data that passes, data that fails
     *
     * @return list<array{string, string, mixed, mixed}>
     */
    public static function rules(): array
    {
        return [
            ['broken-links', 'brokenLinks', ['links' => [], 'scanned' => ['errors' => [], 'passed' => ['HTTP 200' => ['https://a']]]], ['links' => [], 'scanned' => ['errors' => ['HTTP 404' => ['https://a']], 'passed' => []]]],
            ['missing-title', 'metaTitle', 'Hello', ''],
            ['missing-description', 'metaDescription', 'Desc', ''],
            ['missing-canonical', 'canonicalTag', 'https://example.com/', ''],
            ['noindex', 'noindexTag', false, true],
            ['not-https', 'https', true, false],
            ['missing-h1', 'header1', ['One'], []],
            ['multiple-h1', 'header1', ['One'], ['One', 'Two']],
            ['images-without-alt', 'imageAlt', ['images' => [], 'without_alt' => []], ['images' => [], 'without_alt' => ['/a.png']]],
            ['plaintext-email', 'plaintextEmail', [], ['a@b.c']],
            ['underscored-links', 'underscoredLinks', [], ['https://example.com/a_b']],
            ['deprecated-html', 'deprecatedHtml', [], ['center' => 2]],
            ['no-robots-txt', 'robotsFile', "User-agent: *\n", false],
        ];
    }

    #[DataProvider('rules')]
    public function testRulePassesAndFails(string $name, string $check, mixed $pass, mixed $fail): void
    {
        $rule = RuleCatalogue::get($name);
        self::assertSame($name, $rule->name());
        self::assertSame($check, $rule->check());
        self::assertSame(Verdict::PASS, $rule->evaluate($pass)->result);
        self::assertSame(Verdict::FAIL, $rule->evaluate($fail)->result);
        self::assertNotSame('', $rule->evaluate($fail)->message);
    }

    public function testRulesNeverThrowOnUnexpectedShapes(): void
    {
        foreach (RuleCatalogue::all() as $rule) {
            foreach ([null, 42, 'x', [], ['unexpected' => ['nested' => true]]] as $weird) {
                self::assertContains($rule->evaluate($weird)->result, [Verdict::PASS, Verdict::FAIL], $rule->name());
            }
        }
    }

    public function testCatalogueHasThirteenRulesInStableOrder(): void
    {
        self::assertCount(13, RuleCatalogue::all());
        self::assertSame('broken-links', array_key_first(RuleCatalogue::all()));
        self::assertSame('no-robots-txt', array_key_last(RuleCatalogue::all()));
    }

    public function testPresets(): void
    {
        self::assertSame(['broken-links', 'missing-title', 'missing-description', 'missing-canonical', 'not-https'], RuleCatalogue::expand(['recommended']));
        self::assertCount(13, RuleCatalogue::expand(['all']));
        self::assertSame([], RuleCatalogue::expand(['none']));
        self::assertSame([], RuleCatalogue::expand(null));
        self::assertSame(['missing-title', 'noindex'], RuleCatalogue::expand(['noindex', 'missing-title']), 'catalogue order, duplicates removed');
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Unknown rule: bogus');
        RuleCatalogue::expand(['bogus']);
    }
}
