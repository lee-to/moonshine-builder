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

    public function build(): void
    {
        $pagePath = $this->codePath->path(BuildType::INDEX_PAGE->value);

        StubBuilder::make($this->stubFile)->makeFromStub($pagePath->file());
    }
}
