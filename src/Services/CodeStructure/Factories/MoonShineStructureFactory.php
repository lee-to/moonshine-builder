<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Enums\ParseType;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;

final class MoonShineStructureFactory
{
    /**
     * @throws ProjectBuilderException
     */
    public function getStructures(ParseType $type, string $target): CodeStructureList
    {
        $path = '';

        if($type !== ParseType::TABLE) {
            $path = config('moonshine_builder.builds_dir') . '/' . $target;
            if(! file_exists($path)) {
                throw new ProjectBuilderException("File $path not found");
            }
            $extension = pathinfo($path, PATHINFO_EXTENSION);
        } else {
            $extension = 'mysql';
        }

        return match ($extension) {
            'mysql' => StructureFromMysql::make(table: $target, entity: $target,isBelongsTo: true),
            'json' => StructureFromJson::make($path)->makeStructures(),
            default => throw new ProjectBuilderException("$extension extension is not supported")
        };
    }
}
