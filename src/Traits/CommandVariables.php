<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Traits;

use DevLnk\MoonShineBuilder\Services\Builders\Factory\AbstractBuildFactory;
use DevLnk\MoonShineBuilder\Services\CodePath\CodePathContract;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Services\Builders\Factory\MoonShineBuildFactory;

trait CommandVariables
{
    protected function buildFactory(
        CodeStructure $codeStructure,
        CodePathContract $codePath
    ): AbstractBuildFactory {
        return new MoonShineBuildFactory(
            $codeStructure,
            $codePath
        );
    }

    public function generationPath(): string
    {
        return '_default';
    }

    protected function setStubDir(): void
    {
        $this->stubDir = __DIR__ . '/../../stubs/';
    }

    protected function prepareBuilders(): void
    {
        $this->builders = [
            BuildType::MODEL,
            BuildType::RESOURCE,
            BuildType::MIGRATION,
        ];
    }
}
