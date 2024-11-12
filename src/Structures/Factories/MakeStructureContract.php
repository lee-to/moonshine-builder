<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Structures\Factories;

use DevLnk\MoonShineBuilder\Structures\CodeStructureList;

interface MakeStructureContract
{
    public function makeStructures(): CodeStructureList;
}
