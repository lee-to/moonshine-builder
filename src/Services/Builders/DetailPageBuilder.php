<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\DetailPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class DetailPageBuilder extends PageBuilder implements DetailPageBuilderContract
{
    /**
     * @throws FileNotFoundException
     * @throws ProjectBuilderException
     */
    public function build(): void
    {
        $pagePath = $this->codePath->path(BuildType::DETAIL_PAGE->value);
        $resourcePath = $this->codePath->path(BuildType::RESOURCE->value);

        StubBuilder::make($this->stubFile)->makeFromStub($pagePath->file(), [
            '{namespace}' => $pagePath->namespace(),
            '{resource_namespace}' => $resourcePath->namespace(),
            '{class}' => $pagePath->rawName(),
            '{name}' => str_replace('FormPage', '', $pagePath->rawName()),
            '{resource_name}' => $resourcePath->rawName(),
            '{field_uses}' => $this->usesFieldsToResource(),
            '{fields}' => $this->columnsToResource(),
        ]);
    }
}
