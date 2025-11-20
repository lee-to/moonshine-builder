<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\IndexPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class IndexPageBuilder extends PageBuilder implements IndexPageBuilderContract
{
    /**
     * @throws FileNotFoundException
     * @throws ProjectBuilderException
     */
    public function build(): void
    {
        $pagePath = $this->codePath->path(BuildType::INDEX_PAGE->value);
        $resourcePath = $this->codePath->path(BuildType::RESOURCE->value);

        StubBuilder::make($this->stubFile)->makeFromStub($pagePath->file(), [
            '{namespace}' => $pagePath->namespace(),
            '{resource_namespace}' => $resourcePath->namespace(),
            '{class}' => $pagePath->rawName(),
            '{name}' => str_replace('IndexPage', '', $pagePath->rawName()),
            '{resource_name}' => $resourcePath->rawName(),
            '{field_uses}' => $this->usesFieldsToResource(),
            '{fields}' => $this->columnsToResource(),
            '{filters}' => $this->columnsToResource(onlyFilters: true),
        ]);
    }


}
