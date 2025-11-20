<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\ResourceBuilderContract;
use DevLnk\MoonShineBuilder\Services\CodeStructure\MoonShineStructure;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class ResourceBuilder extends AbstractBuilder implements ResourceBuilderContract
{
    private MoonShineStructure $moonShineStructure;

    /**
     * @throws FileNotFoundException
     * @throws ProjectBuilderException
     */
    public function build(): void
    {
        $this->moonShineStructure = new MoonShineStructure($this->codeStructure);

        $resourcePath = $this->codePath->path(BuildType::RESOURCE->value);
        $modelPath = $this->codePath->path(BuildType::MODEL->value);

        $modelUse = "use {$modelPath->namespace()}\\{$modelPath->rawName()};";

        $withArray = $this->withArray();

        StubBuilder::make($this->stubFile)
            ->setKey(
                '{column}',
                $this->moonShineStructure->getColumnName(),
                $this->codeStructure->getColumnName() !== null
            )
            ->setKey(
                '{model_use}',
                $modelUse
            )
            ->setKey(
                '{with_array}',
                "\n\n\tprotected array \$with = [{with}];",
                $withArray !== ''
            )
            ->makeFromStub($resourcePath->file(), [
                '{namespace}' => $resourcePath->namespace(),
                '{class}' => $resourcePath->rawName(),
                '{model}' => $modelPath->rawName(),
                '{name}' => str_replace('Resource', '', $resourcePath->rawName()),
                '{resourceTitle}' => $this->moonShineStructure->getResourceTitle(),
                '{with}' => $this->withArray(),
            ]);
    }

    public function withArray(): string
    {
        $withArray = array_map(fn($with) => "'$with'", $this->moonShineStructure->getWithProperty());
        return implode(', ', $withArray);
    }
}
