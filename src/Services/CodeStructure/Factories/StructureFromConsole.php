<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\ColumnStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\RelationStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;

final readonly class StructureFromConsole implements MakeStructureContract
{
    public function __construct(
        private string $entity,

        /** @var array<int, array{column: string, name:string, type:string, relationTable:string}> */
        private array $fieldValues,

        private bool $isMigration = false,
        private bool $isTimestamps = false,
        private bool $isSoftDelete = false,
    ) {
    }

    public function makeStructures(): CodeStructureList
    {
        $codeStructures = new CodeStructureList();

        $table = str($this->entity)->replace('Resource', '')->snake()->lower()->plural()->value();
        $name = str($this->entity)->replace('Resource', '')->camel()->singular()->value();

        $codeStructure = new CodeStructure($table, $name);

        $codeStructure->setWithMigration($this->isMigration);
        $codeStructure->setWithModel(true);
        $codeStructure->setWithResource(true);

        $codeStructure->setColumnName('column');

        foreach ($this->fieldValues as $value) {
            $columnStructure = new ColumnStructure(
                column: $value['column'],
                name: $value['name'],
                type: SqlTypeMap::from($value['type']),
                default: null,
                nullable: false,
                required: true,
            );

            if (! empty($value['relationTable'])) {
                if (
                    $columnStructure->type() === SqlTypeMap::BELONGS_TO
                    || $columnStructure->type() === SqlTypeMap::BELONGS_TO_MANY
                ) {
                    $foreignKey = 'id';
                } else {
                    $foreignKey = str($name)->snake()->lower()->value();
                }

                $columnStructure->setRelation(new RelationStructure(
                    foreignColumn: $foreignKey,
                    modelRelationName: null,
                    table: $value['relationTable'],
                ));
            }

            $codeStructure->addColumn($columnStructure);
        }

        if ($this->isTimestamps) {
            $createdAtField = new ColumnStructure(
                column: 'created_at',
                name: 'Created at',
                type: SqlTypeMap::TIMESTAMP,
                default: null,
                nullable: true,
                required: false,
            );
            $codeStructure->addColumn($createdAtField);

            $updatedAtField = new ColumnStructure(
                column: 'updated_at',
                name: 'Updated at',
                type: SqlTypeMap::TIMESTAMP,
                default: null,
                nullable: true,
                required: false,
            );
            $codeStructure->addColumn($updatedAtField);
        }

        if ($this->isSoftDelete) {
            $softDeletes = new ColumnStructure(
                column: 'deleted_at',
                name: 'Deleted at',
                type: SqlTypeMap::TIMESTAMP,
                default: null,
                nullable: true,
                required: false,
            );
            $codeStructure->addColumn($softDeletes);
        }

        $codeStructures->addCodeStructure($codeStructure);

        return $codeStructures;
    }
}