<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Enums\ParseType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;

final class MoonShineStructureFactory
{
    /**
     * @throws ProjectBuilderException
     */
    public function getStructures(ParseType $type, string $target): MakeStructureContract
    {
        return match ($type) {
            ParseType::TABLE => StructureFromMysql::make(table: $target, entity: $target,isBelongsTo: true),
            ParseType::JSON => StructureFromJson::make($this->getPath($target)),
//            ParseType::OPENAPI => StructureFromOpenapi::make($this->getPath($target)),
            default => throw new ProjectBuilderException('Parse type not found')
        };
    }

    /**
     * @throws ProjectBuilderException
     */
    private function getPath(string $target): string
    {
        $result = config('moonshine_builder.builds_dir') . '/' . $target;
        if(! file_exists($result)) {
            throw new ProjectBuilderException("File $result not found");
        }
        return $result;
    }
}
