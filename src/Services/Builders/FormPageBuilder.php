<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\FormPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\ResourceBuilderContract;
use DevLnk\MoonShineBuilder\Services\CodeStructure\MoonShineStructure;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class FormPageBuilder extends AbstractBuilder implements FormPageBuilderContract
{
    private MoonShineStructure $moonShineStructure;

    public function build(): void
    {
        $pagePath = $this->codePath->path(BuildType::FORM_PAGE->value);

        StubBuilder::make($this->stubFile)->makeFromStub($pagePath->file());
    }
}
