<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Enums;

enum BuildType: string implements BuildTypeContract
{
    case MODEL = 'model';

    case MIGRATION = 'migration';

    case RESOURCE = 'resource';

    public function stub(): string
    {
        return match ($this) {
            self::MODEL => 'Model',
            self::MIGRATION => 'Migration',
            self::RESOURCE => 'ModelResourceDefault',
        };
    }

    public function value(): string
    {
        return $this->value;
    }
}
