<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use Cassandra\Index;
use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\IndexPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\ResourceBuilderContract;
use DevLnk\MoonShineBuilder\Services\CodeStructure\MoonShineStructure;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class IndexPageBuilder extends AbstractBuilder implements IndexPageBuilderContract
{
    private MoonShineStructure $moonShineStructure;

    /**
     * @throws FileNotFoundException
     * @throws ProjectBuilderException
     */
    public function build(): void
    {
        $this->moonShineStructure = new MoonShineStructure($this->codeStructure);

        $pagePath = $this->codePath->path(BuildType::INDEX_PAGE->value);

        StubBuilder::make($this->stubFile)->makeFromStub($pagePath->file(), [
            '{namespace}' => $pagePath->namespace(),
            '{class}' => $pagePath->rawName(),
            '{name}' => str_replace('IndexPage', '', $pagePath->rawName()),
            '{field_uses}' => $this->usesFieldsToResource(),
            '{fields}' => $this->columnsToResource(),
            '{filters}' => $this->columnsToResource(onlyFilters: true),
        ]);
    }

    /**
     * @throws ProjectBuilderException
     */
    protected function usesFieldsToResource(): string
    {
        $result = "";

        foreach ($this->moonShineStructure->getUsesForFields() as $use) {
            $result .= str($use)->newLine()->value();
        }

        return $result;
    }

    /**
     * @throws ProjectBuilderException
     */
    protected function columnsToResource(bool $onlyFilters = false): string
    {
        $result = "";

        foreach ($this->moonShineStructure->getFields(tabulation: 4, onlyFilters: $onlyFilters) as $field) {
            $result .= str($field)
                ->prepend("\t\t\t")
                ->prepend("\n")
                ->append(',')
                ->value()
            ;
        }

        return $result;
    }
}
