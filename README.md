# polyglot/template-resolver-simple

> A simple [polyglot](https://packagist.org/packages/polyglot/) template resolver.

# Install

```shell
composer require polyglot/template-resolver-simple:^1.0
```

# Using

```php
<?php

use Polyglot\SimpleTemplateResolver\PluralHandler;
use Polyglot\SimpleTemplateResolver\SimpleTemplateResolver;
/**
 * @var string $prefix Prefix of parameter name in template, by default, "{"
 * @var string $suffix Suffix of parameter name in template, by default, "}"
 * @var string $pluralParameter Plural parameter name in template, by default, "count"
 * @var string $templateDelimiter Plural template delimiter, by default, "|"
 * @var PluralDetectorRegistry $pluralDetectorRegistry Plural detector registry
 */
$pluralHandler = new PluralHandler($pluralDetectorRegistry, $pluralParameter, $templateDelimiter);
$resolver = new SimpleTemplateResolver($prefix, $suffix, $pluralHandler);

/**
 * @var string $template Translation template
 * @var array<string, string|Stringable> $parameters Translation parameters
 * @var string $locale Translation locale
 */
$translation = $resolver->resolve($template, $parameters, $locale);
```

# Examples

```php
/*
 * Default-style templates
 */
$resolver = new \Polyglot\SimpleTemplateResolver\SimpleTemplateResolver();

$translation = $resolver->resolve('Hello, {name}!', ['name' => 'polyglot'], 'en_US'); // returns "Hello, polyglot!"

/*
 * Laravel-style templates
 */
$resolver = new \Polyglot\SimpleTemplateResolver\SimpleTemplateResolver(':', '');
$resolver->addFilter('ucfirst');
$resolver->addFilter('strtoupper');

$translation = $resolver->resolve('Hello, :name!', ['name' => 'polyglot'], 'en_US'); // returns "Hello, polyglot!"
$translation = $resolver->resolve('Hello, :Name!', ['name' => 'polyglot'], 'en_US'); // returns "Hello, Polyglot!"
$translation = $resolver->resolve('Hello, :NAME!', ['name' => 'polyglot'], 'en_US'); // returns "Hello, POLYGLOT!"

/*
 * Old Symfony-style templates
 */
$resolver = new \Polyglot\SimpleTemplateResolver\SimpleTemplateResolver('%', '%');
$resolver->addReplacement('%%', '%');

$translation = $resolver->resolve('%percent%%% match!', ['percent' => 100], 'en_US'); // returns "100% match!"

/*
 * Plural
 */

$registry = new \Polyglot\CldrCardinalPluralDetectorRegistry\CldrCardinalPluralDetectorRegistry();
$plural = new \Polyglot\SimpleTemplateResolver\PluralHandler($registry, 'count', '|');
$resolver = new \Polyglot\SimpleTemplateResolver\SimpleTemplateResolver('%', '%', $plural);
$template = '[-Inf,0[interval-negative'
    . '|plural-one'
    . '|plural-other'
    . '|{0}map-zero'
    . '|{10}map-ten'
    . '|]10,50[interval-10-50'
    . '|[50,Inf]interval-from-50'
;

$resolver->resolve($template, ['count' => rand(-INF, -.0001)], 'en_US');// returns 'interval-negative'
$resolver->resolve($template, ['count' => 0], 'en_US');// returns 'map-zero'
$resolver->resolve($template, ['count' => 1], 'en_US');// returns 'plural-one'
$resolver->resolve($template, ['count' => rand(2, 9)], 'en_US');// returns 'plural-other'
$resolver->resolve($template, ['count' => 10], 'en_US');// returns 'map-ten'
$resolver->resolve($template, ['count' => rand(11, 49)], 'en_US');// returns 'interval-10-50'
$resolver->resolve($template, ['count' => rand(50, INF)], 'en_US');// returns 'interval-from-50'
$resolver->resolve($template, [], 'en_US');// returns $template

```

