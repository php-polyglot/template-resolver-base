<?php

declare(strict_types=1);

namespace Polyglot\SimpleTemplateResolver;

use Polyglot\Contract\PluralDetectorRegistry\Exception\LocaleNotSupported;
use Polyglot\Contract\PluralDetectorRegistry\PluralDetectorRegistry;

final class PluralHandler
{
    private PluralDetectorRegistry $pluralDetectorRegistry;
    private string $parameterName;
    private string $templateDelimiter;
    private array $cache = [];
    private ?int $cacheItemsLimit;

    public function __construct(
        PluralDetectorRegistry $pluralDetectorRegistry,
        string $parameterName = 'count',
        string $templateDelimiter = '|',
        ?int $cacheItemsLimit = 1024
    ) {
        $this->pluralDetectorRegistry = $pluralDetectorRegistry;
        $this->parameterName = $parameterName;
        $this->templateDelimiter = $templateDelimiter;
        $this->cacheItemsLimit = $cacheItemsLimit;
    }

    public function isNeedPluralize(array $parameters): bool
    {
        return array_key_exists($this->parameterName, $parameters);
    }

    public function getTemplate(string $parentTemplate, array $parameters, string $locale): string
    {
        $variants = $this->getPluralTemplate($parentTemplate);
        $mapKey = (string)$parameters[$this->parameterName];
        if (array_key_exists($mapKey, $variants['map'])) {
            return $variants['map'][$mapKey];
        }

        $number = (float)$parameters[$this->parameterName];
        foreach ($variants['rules'] as $rule) {
            if ($rule['rule']($number)) {
                return $rule['template'];
            }
        }

        $pluralCategory = $this->getPluralCategory($locale, $parameters[$this->parameterName]);
        if (array_key_exists($pluralCategory, $variants['plural'])) {
            return $variants['plural'][$pluralCategory];
        }

        return $variants['default'];
    }

    /**
     * @param string $template
     * @return array{
     *     map:array<string, string>,
     *     rules: iterable<array{template:string, rule:callable(float): bool}>,
     *     plural:array<int, string>,
     *     default: string,
     * }
     */
    private function getPluralTemplate(string $template): array
    {
        $key = sha1($template);
        $pluralTemplate = $this->getFromCache($key);
        if (is_null($pluralTemplate)) {
            $pluralTemplate = $this->parsePluralTemplate($template);
            $this->saveToCache($key, $pluralTemplate);
            $this->cache[$key] = $this->parsePluralTemplate($template);
        }
        return $pluralTemplate;
    }

    private function getFromCache(string $key): ?array
    {
        if (!array_key_exists($key, $this->cache)) {
            return null;
        }
        return $this->cache[$key];
    }

    private function saveToCache(string $key, array $data): void
    {
        if ($this->cacheItemsLimit === 0) {
            return;
        }

        if (!is_null($this->cacheItemsLimit)) {
            while (count($this->cache) >= $this->cacheItemsLimit) {
                $first = array_keys($this->cache)[0] ?? null;
                if (is_null($first)) {
                    continue;
                }
                unset($this->cache[$first]);
            }
        }
        $this->cache[$key] = $data;
    }

    /**
     * @param string $template
     * @return array{
     *     map:array<string, string>,
     *     rules: iterable<array{template:string, rule:callable(float): bool}>,
     *     plural:array<int, string>,
     *     default: string,
     * }
     */
    private function parsePluralTemplate(string $template): array
    {
        $intervalRegexp = $this->getRegexp();

        $parts = explode($this->templateDelimiter, $template);
        $result = [
            'map' => [],
            'rules' => [],
            'plural' => [],
        ];

        $default = [];
        foreach ($parts as $part) {
            if (preg_match($intervalRegexp, $part, $matches)) {
                if ($matches[2]) {
                    foreach (explode(',', $matches[3]) as $n) {
                        $result['map'][trim($n)] = $matches['template'];
                    }
                } else {
                    $from = $this->getLimit($matches['left']);
                    $to = $this->getLimit($matches['right']);
                    $includeFrom = $matches['left_delimiter'] === '[';
                    $includeTo = $matches['right_delimiter'] === ']';
                    $rule = static function (float $number) use ($from, $to, $includeFrom, $includeTo): bool {
                        if ($includeFrom && $number == $from) {
                            return true;
                        }
                        if ($includeTo && $number == $to) {
                            return true;
                        }
                        return $number > $from && $number < $to;
                    };
                    $result['rules'][] = [
                        'rule' => $rule,
                        'template' => $matches['template']
                    ];
                }
                $default[] = $matches['template'];
            } else {
                $result['plural'][] = $part;
                $default[] = $part;
            }
        }

        $result['default'] = $default[0] ?? $template;
        return $result;
    }

    private function getLimit(string $limit): float
    {
        if ($limit === '-Inf') {
            return -INF;
        }
        if ($limit === 'Inf') {
            return INF;
        }
        return (float)$limit;
    }

    private function getPluralCategory(string $locale, $number): int
    {
        try {
            $pluralDetector = $this->pluralDetectorRegistry->get($locale);
        } catch (LocaleNotSupported $exception) {
            return 0;
        }

        /** @var array<string, int> $map */
        $map = [];
        $idx = 0;
        $pluralCategory = $pluralDetector->detect($number);
        foreach ($pluralDetector->getAllowedCategories() as $category) {
            $map[$category] = $idx;
            $idx++;
        }
        return $map[$pluralCategory] ?? 0;
    }

    private function getRegexp(): string
    {
        /**
         * The interval regexp are derived from code of the Symfony Translation component v4.4,
         * which is subject to the MIT license
         */
        return <<<REGEXP
        /^(?P<interval>
            ({\s*
                (-?\d+(\.\d+)?(\s*,\s*-?\d+(.\d+)?)*)
            \s*})
        
                |
        
            (?P<left_delimiter>[\[\]])
                \s*
                (?P<left>-Inf|-?\d+(\.\d+)?)
                \s*,\s*
                (?P<right>\+?Inf|-?\d+(\.\d+)?)
                \s*
            (?P<right_delimiter>[\[\]])
        )\s*(?P<template>.*?)$/xs
        REGEXP;
    }
}
