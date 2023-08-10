<?php

declare(strict_types=1);

namespace Polyglot\SimpleTemplateResolver;

use Polyglot\Contract\TemplateResolver\TemplateResolver;

final class SimpleTemplateResolver implements TemplateResolver
{
    private string $prefix;
    private string $suffix;
    private int $prefixLength;
    private int $suffixLength;
    private array $replacements = [];

    /** @var iterable<callable(string):string> */
    private array $filters = [];
    private ?PluralHandler $pluralHandler;

    public function __construct(
        string $prefix = '{',
        string $suffix = '}',
        ?PluralHandler $pluralHandler = null
    ) {
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->prefixLength = strlen($this->prefix);
        $this->suffixLength = strlen($this->suffix);
        $this->pluralHandler = $pluralHandler;
        $this->addFilter('strval');
    }

    public function addReplacement(string $search, string $replace): self
    {
        $this->replacements[$search] = $replace;
        return $this;
    }

    /**
     * @param callable(string):string $filter
     * @return $this
     */
    public function addFilter(callable $filter): self
    {
        $this->filters[] = $filter;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $template, array $parameters, string $locale): string
    {
        $translated = $this->getTemplate($template, $parameters, $locale);

        $pairs = $this->getReplacements($parameters);
        if (!empty($pairs)) {
            $translated = strtr($translated, $pairs);
        }

        if (!empty($this->replacements)) {
            $translated = strtr($translated, $this->replacements);
        }

        return $translated;
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function getReplacements(array $parameters): array
    {
        $pairs = [];
        foreach ($parameters as $parameterName => $value) {
            foreach ($this->filters as $filter) {
                $preparedName = $filter($parameterName);
                $preparedValue = $filter($value);
                if ($preparedName === '') {
                    continue;
                }
                $pairs[$this->wrapParameterName($preparedName)] = (string)$preparedValue;
            }
        }
        return $pairs;
    }

    private function wrapParameterName(string $parameterName): string
    {
        if ($this->prefixLength > 0 && substr($parameterName, 0, $this->prefixLength) !== $this->prefix) {
            $parameterName = sprintf('%s%s', $this->prefix, $parameterName);
        }
        if ($this->suffixLength > 0 && substr($parameterName, -$this->suffixLength) !== $this->suffix) {
            $parameterName = sprintf('%s%s', $parameterName, $this->suffix);
        }
        return $parameterName;
    }

    private function getTemplate(string $template, array $parameters, string $locale): string
    {
        if (!is_null($this->pluralHandler) && $this->pluralHandler->isNeedPluralize($parameters)) {
            return $this->pluralHandler->getTemplate($template, $parameters, $locale);
        }
        return $template;
    }
}
