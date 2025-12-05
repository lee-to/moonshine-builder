<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodePath\MoonShine;

use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Services\CodePath\AbstractPathItem;

readonly class IndexPagePath extends AbstractPathItem
{
    public function getBuildAlias(): string
    {
        return BuildType::INDEX_PAGE->value;
    }
}
