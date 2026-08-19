<?php

namespace SEOCheckup\Cli;

/**
 * Every Rule the CLI knows, keyed by name, plus the --fail-on presets.
 */
final class RuleCatalogue
{
    public const RECOMMENDED = ['broken-links', 'missing-title', 'missing-description', 'missing-canonical', 'not-https'];

    /** @var array<string, Rule>|null */
    private static ?array $rules = null;

    /** @return array<string, Rule> name => rule, in report order */
    public static function all(): array
    {
        if (self::$rules === null) {
            $list = [
                new Rules\BrokenLinks(),
                new Rules\MissingTitle(),
                new Rules\MissingDescription(),
                new Rules\MissingCanonical(),
                new Rules\Noindex(),
                new Rules\NotHttps(),
                new Rules\MissingH1(),
                new Rules\MultipleH1(),
                new Rules\ImagesWithoutAlt(),
                new Rules\PlaintextEmail(),
                new Rules\UnderscoredLinks(),
                new Rules\DeprecatedHtml(),
                new Rules\NoRobotsTxt(),
            ];
            self::$rules = [];
            foreach ($list as $rule) {
                self::$rules[$rule->name()] = $rule;
            }
        }

        return self::$rules;
    }

    public static function get(string $name): Rule
    {
        return self::all()[$name] ?? throw new UsageException("Unknown rule: {$name}");
    }

    /**
     * Presets and names → rule names in catalogue order, de-duplicated.
     *
     * @param list<string>|null $selection
     * @return list<string>
     */
    public static function expand(?array $selection): array
    {
        if ($selection === null) {
            return [];
        }
        $wanted = [];
        foreach ($selection as $item) {
            $wanted = match ($item) {
                'all'         => [...$wanted, ...array_keys(self::all())],
                'recommended' => [...$wanted, ...self::RECOMMENDED],
                'none'        => $wanted,
                default       => [...$wanted, self::get($item)->name()],
            };
        }

        return array_values(array_filter(array_keys(self::all()), fn ($n) => in_array($n, $wanted, true)));
    }
}
