<?php

declare(strict_types=1);

namespace TestUnits\Polyglot\SimpleTemplateResolver;

use PHPUnit\Framework\TestCase;
use Polyglot\CldrCardinalPluralDetectorRegistry\CldrCardinalPluralDetectorRegistry;
use Polyglot\SimpleTemplateResolver\PluralHandler;
use Polyglot\SimpleTemplateResolver\SimpleTemplateResolver;

final class SimpleTemplateResolverTest extends TestCase
{
    /**
     * @dataProvider provideResolveWithReplaces
     */
    public function testResolveWithReplaces(
        string $locale,
        string $template,
        array $parameters,
        string $translation,
        string $prefix = '{',
        string $suffix = '}',
        array $replacements = [],
        array $filters = []
    ): void {
        $templateResolver = new SimpleTemplateResolver($prefix, $suffix);
        foreach ($replacements as $search => $replace) {
            $templateResolver->addReplacement($search, $replace);
        }
        foreach ($filters as $filter) {
            $templateResolver->addFilter($filter);
        }

        $this->assertSame($translation, $templateResolver->resolve($template, $parameters, $locale));
    }

    /**
     * @dataProvider provideResolveWithoutReplaces
     */
    public function testResolveWithoutReplaces(
        string $locale,
        string $template,
        array $parameters,
        string $prefix = '{',
        string $suffix = '}'
    ): void {
        $templateResolver = new SimpleTemplateResolver($prefix, $suffix);
        $this->assertSame($template, $templateResolver->resolve($template, $parameters, $locale));
    }

    /**
     * @dataProvider provideResolvePlural
     */
    public function testResolvePlural(
        string $locale,
        string $template,
        array $parameters,
        string $translation,
        string $prefix = '%',
        string $suffix = '%',
        ?PluralHandler $pluralHandler = null
    ): void {
        $templateResolver = new SimpleTemplateResolver($prefix, $suffix, $pluralHandler);
        $this->assertSame($translation, $templateResolver->resolve($template, $parameters, $locale));
    }

    /**
     * @param string $locale
     * @param string $template
     * @param array $parameters
     * @param string $translation
     * @param PluralHandler|null $pluralHandler
     * @return void
     * @dataProvider provideResolveRules
     */
    public function testResolveRules(
        string $locale,
        string $template,
        array $parameters,
        string $translation,
        ?PluralHandler $pluralHandler = null
    ): void {
        $templateResolver = new SimpleTemplateResolver('%', '%', $pluralHandler);
        $this->assertSame($translation, $templateResolver->resolve($template, $parameters, $locale));
    }

    public function provideResolveWithReplaces(): iterable
    {
        return [
            ['en_US', 'hello, {name}!', ['name' => 'phpunit'], 'hello, phpunit!'],
            ['en_US', 'hello, %name%!', ['name' => 'phpunit'], 'hello, phpunit!', '%', '%'],
            ['en_US', 'hello, %name%!', ['%name%' => 'phpunit'], 'hello, phpunit!', '%', '%'],
            ['en_US', 'hello, {{name}}!', ['name' => 'phpunit'], 'hello, phpunit!', '{{', '}}'],
            ['en_US', 'hello, {{ name }}!', ['name' => 'phpunit'], 'hello, phpunit!', '{{ ', ' }}'],
            ['en_US', 'hello, :name!', ['name' => 'phpunit'], 'hello, phpunit!', ':', ''],
            ['en_US', 'hello, :Name!', ['name' => 'phpunit'], 'hello, Phpunit!', ':', '', [], ['ucfirst']],
            ['en_US', 'hello, :NAME!', ['name' => 'phpunit'], 'hello, PHPUNIT!', ':', '', [], ['strtoupper']],
            ['en_US', '%percent%%% match!', ['percent' => 99], '99% match!', '%', '%', ['%%' => '%']],
            ['en_US', 'test %%', ['' => 99], 'test %', '%', '%', ['%%' => '%']],
            ['en_US', 'hello, world', ['hello' => 'world', 'world' => 'hello'], 'world, hello', '', ''],
        ];
    }

    public function provideResolveWithoutReplaces(): iterable
    {
        return [
            ['en_US', 'hello, world', []],
            ['en_US', 'hello, world', ['hello' => 'world', 'world' => 'hello']],
            ['en_US', 'hello, world', ['hello' => 'world', 'world' => 'hello'], '%', '%'],
            ['en_US', 'hello, %world%', ['name' => 'phpunit'], '%', '%'],
            ['en_US', 'hello, :world', ['name' => 'phpunit'], ':', ''],
        ];
    }

    public function provideResolvePlural(): iterable
    {
        $pluralDetectorRegistry = new CldrCardinalPluralDetectorRegistry();
        $pluralHandler = new PluralHandler($pluralDetectorRegistry);
        $template = '%count% zero|%count% one|%count% two|%count% few|%count% many|%count% other';
        yield ['ar', $template, ['count' => 0], '0 zero', '%', '%', $pluralHandler];
        yield ['ar', $template, ['count' => 1], '1 one', '%', '%', $pluralHandler];
        yield ['ar', $template, ['count' => 2], '2 two', '%', '%', $pluralHandler];
        for ($number = 3; $number < 10; $number++) {
            yield ['ar', $template, ['count' => $number], sprintf('%d few', $number), '%', '%', $pluralHandler];
        }
        for ($number = 11; $number < 99; $number++) {
            yield ['ar', $template, ['count' => $number], sprintf('%d many', $number), '%', '%', $pluralHandler];
        }
        yield ['ar', $template, ['count' => 1.1], '1.1 other', '%', '%', $pluralHandler];
        yield ['ar', $template, ['count' => 1], '1 zero|1 one|1 two|1 few|1 many|1 other', '%', '%', null];
    }

    public function provideResolveRules(): iterable
    {
        $pluralDetectorRegistry = new CldrCardinalPluralDetectorRegistry();
        $pluralHandler = new PluralHandler($pluralDetectorRegistry);
        $locale = 'en_US';
        $template = '[-Inf,0[interval-negative'
            . '|{0}map-zero'
            . '|plural-one'
            . '|plural-other'
            . '|{10}map-ten'
            . '|]10,50[interval-10-50'
            . '|]50,Inf]interval-from-50'
        ;

        yield [$locale, $template, ['count' => -INF], 'interval-negative', $pluralHandler];
        yield [$locale, $template, ['count' => -1], 'interval-negative', $pluralHandler];
        yield [$locale, $template, ['count' => -1.999], 'interval-negative', $pluralHandler];
        yield [$locale, $template, ['count' => 0], 'map-zero', $pluralHandler];
        yield [$locale, $template, ['count' => 1], 'plural-one', $pluralHandler];
        for ($number = 2; $number < 10; $number++) {
            yield [$locale, $template, ['count' => $number], 'plural-other', $pluralHandler];
        }
        yield [$locale, $template, ['count' => 10], 'map-ten', $pluralHandler];
        for ($number = 11; $number < 50; $number++) {
            yield [$locale, $template, ['count' => $number], 'interval-10-50', $pluralHandler];
        }
        yield [$locale, $template, ['count' => 50], 'plural-other', $pluralHandler];
        yield [$locale, $template, ['count' => 51], 'interval-from-50', $pluralHandler];
        yield [$locale, $template, ['count' => 500], 'interval-from-50', $pluralHandler];
        yield [$locale, $template, ['count' => INF], 'interval-from-50', $pluralHandler];
        yield [$locale, $template, [], $template];

        $parts = [
            '[-Inf,10[to-10',
            '{10}map-ten',
            ']11,50[from-11',
        ];

        yield [$locale, implode('|', $parts), ['count' => 11], 'to-10', $pluralHandler];
        yield [$locale, implode('|', [$parts[1], $parts[0], $parts[2]]), ['count' => 11], 'map-ten', $pluralHandler];
        yield [$locale, implode('|', [$parts[2], $parts[0], $parts[1]]), ['count' => 11], 'from-11', $pluralHandler];
    }
}
