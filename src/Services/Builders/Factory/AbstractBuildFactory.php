<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders\Factory;

use DevLnk\MoonShineBuilder\Services\CodePath\CodePathContract;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;

abstract readonly class AbstractBuildFactory
{
    public function __construct(
        protected CodeStructure $codeStructure,
        protected CodePathContract $codePath,
    ) {
    }

    abstract public function call(string $buildType, string $stub): void;
}
