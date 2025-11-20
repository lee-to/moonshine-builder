<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\CodeStructure\MoonShineStructure;

abstract class PageBuilder extends AbstractBuilder
{
    private ?MoonShineStructure $moonShineStructure = null;

    private function getMoonshineStructure(): MoonShineStructure
    {
        if($this->moonShineStructure === null) {
            $this->moonShineStructure = new MoonShineStructure($this->codeStructure);
        }

        return $this->moonShineStructure;
    }

    /**
     * @throws ProjectBuilderException
     */
    final protected function usesFieldsToResource(): string
    {
        $result = "";

        foreach ($this->getMoonshineStructure()->getUsesForFields() as $use) {
            $result .= str($use)->newLine()->value();
        }

        return $result;
    }

    /**
     * @throws ProjectBuilderException
     */
    final protected function columnsToResource(bool $onlyFilters = false): string
    {
        $result = "";

        foreach ($this->getMoonshineStructure()->getFields(tabulation: 4, onlyFilters: $onlyFilters) as $field) {
            $result .= str($field)
                ->prepend("\t\t\t")
                ->prepend("\n")
                ->append(',')
                ->value()
            ;
        }

        return $result;
    }

    final protected function columnsToRules(): string
    {
        $result = "";

        foreach ($this->getMoonshineStructure()->getRules() as $rule) {
            $result .= str($rule)
                ->prepend("\t\t\t")
                ->prepend("\n")
                ->append(',')
                ->value()
            ;
        }

        return $result;
    }
}