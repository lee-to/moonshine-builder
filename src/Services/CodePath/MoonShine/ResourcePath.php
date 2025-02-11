<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodePath\MoonShine;

use DevLnk\MoonShineBuilder\Services\CodePath\AbstractPathItem;
use DevLnk\MoonShineBuilder\Enums\BuildType;

readonly class ResourcePath extends AbstractPathItem
{
    public function getBuildAlias(): string
    {
        return BuildType::RESOURCE->value;
    }
}
