<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Services\CodePath\CodePathContract;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Support\Traits\Makeable;

/** @phpstan-consistent-constructor */
abstract class AbstractBuilder implements BuilderContract
{
    use Makeable;

    public function __construct(
        protected CodeStructure $codeStructure,
        protected CodePathContract $codePath,
        protected string $stubFile,
    ) {
    }
}
