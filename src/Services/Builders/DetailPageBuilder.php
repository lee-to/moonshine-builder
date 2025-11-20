<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\DetailPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\ResourceBuilderContract;
use DevLnk\MoonShineBuilder\Services\CodeStructure\MoonShineStructure;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class DetailPageBuilder extends AbstractBuilder implements DetailPageBuilderContract
{
    private MoonShineStructure $moonShineStructure;

    public function build(): void
    {
        $pagePath = $this->codePath->path(BuildType::DETAIL_PAGE->value);

        StubBuilder::make($this->stubFile)->makeFromStub($pagePath->file());
    }
}
