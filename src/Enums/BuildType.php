<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Enums;

enum BuildType: string implements BuildTypeContract
{
    case MODEL = 'model';

    case MIGRATION = 'migration';

    case RESOURCE = 'resource';

    case INDEX_PAGE = 'index-page';

    case FORM_PAGE = 'form-page';

    case DETAIL_PAGE = 'detail-page';

    public function stub(): string
    {
        return match ($this) {
            self::MODEL => 'Model',
            self::MIGRATION => 'Migration',
            self::RESOURCE => 'ModelResourceDefault',
            self::INDEX_PAGE => 'IndexPage',
            self::FORM_PAGE => 'FormPage',
            self::DETAIL_PAGE => 'DetailPage',
        };
    }

    public function value(): string
    {
        return $this->value;
    }
}
