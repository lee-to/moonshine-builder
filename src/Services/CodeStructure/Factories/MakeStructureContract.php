<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;

interface MakeStructureContract
{
    public function makeStructures(): CodeStructureList;
}
