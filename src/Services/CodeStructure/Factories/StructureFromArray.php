<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;
use DevLnk\MoonShineBuilder\Services\CodeStructure\ColumnStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\RelationStructure;
use Throwable;
use ValueError;

final readonly class StructureFromArray implements MakeStructureContract
{
    public function __construct(
        private array $data
    ) {
    }

    /**
     * @throws ProjectBuilderException
     */
    public function makeStructures(): CodeStructureList
    {
        $codeStructures = new CodeStructureList();

        if(! isset($this->data['resources'])) {
            throw new ProjectBuilderException('No resources array found.');
        }

        foreach ($this->data['resources'] as $resource) {
            $table = $resource['table'] ?? str($resource['name'])->snake()->lower()->plural()->value();

            $codeStructure = new CodeStructure($table, $resource['name']);

            if(isset($resource['withModel'])) {
                $codeStructure->setWithModel($resource['withModel']);
            }

            if(isset($resource['withMigration'])) {
                $codeStructure->setWithMigration($resource['withMigration']);
            }

            if(isset($resource['withResource'])) {
                $codeStructure->setWithResource($resource['withResource']);
            }

            if(isset($resource['menuName'])) {
                $codeStructure->setMenuName($resource['menuName']);
            }

            $codeStructure->setColumnName($resource['column'] ?? null);

            foreach ($resource['fields'] as $field) {
                try {
                    $type = SqlTypeMap::from($field['type']);
                } catch (ValueError) {
                    throw new ProjectBuilderException("For column '{$field['column']}' the wrong type '{$field['type']}' is set.");
                }

                $columnStructure = new ColumnStructure(
                    column: $field['column'],
                    name: $field['name'] ?? '',
                    type: $type,
                    default: isset($field['default']) ? (string) $field['default'] : null,
                    nullable: $field['nullable'] ?? false,
                    required: $field['required'] ?? false,
                );

                if(! empty($field['relation'])) {
                    if(
                        ! isset($field['relation']['foreign_key'])
                        && (
                            $columnStructure->type() === SqlTypeMap::BELONGS_TO
                            || $columnStructure->type() === SqlTypeMap::BELONGS_TO_MANY
                        )
                    ) {
                        $field['relation']['foreign_key'] = 'id';
                    }

                    if(! isset($field['relation']['foreign_key'])) {
                        throw new ProjectBuilderException("For column '{$field['column']}' in the relation parameter, you must specify 'foreign_key'.");
                    }

                    $columnStructure->setRelation(new RelationStructure(
                        $field['relation']['foreign_key'],
                        $field['relation']['table'],
                    ));

                    if(! empty($field['relation']['relation_name'])) {
                        $columnStructure->setRelationName($field['relation']['relation_name']);
                    }
                }

                if(isset($field['hasFilter'])) {
                    $columnStructure->setHasFilter($field['hasFilter']);
                }

                if(isset($field['default'])) {
                    $defaultValue = $columnStructure->inputType() === 'text'
                        ? "default('{$field['default']}')"
                        : "default({$field['default']})"
                    ;

                    if(! isset($field['methods'])) {
                        $field['methods'][] = $defaultValue;
                    } else {
                        array_unshift($field['methods'], $defaultValue);
                    }

                    if(! isset($field['migration']['methods'])) {
                        $field['migration']['methods'][] = $defaultValue;
                    } else {
                        array_unshift($field['migration']['methods'], $defaultValue);
                    }
                }

                if(! empty($field['migration'])) {
                    if(! empty($field['migration']['options'])) {
                        $columnStructure->setMigrationOptions($field['migration']['options']);
                    }

                    if(! empty($field['migration']['methods'])) {
                        $columnStructure->setMigrationMethods($field['migration']['methods']);
                    }
                }

                if(! empty($field['resource_class'])) {
                    $columnStructure->setResourceClass($field['resource_class']);
                }

                if(! empty($field['model_class'])) {
                    $columnStructure->setModelClass($field['model_class']);
                }

                if(! empty($field['methods'])) {
                    $columnStructure->setResourceMethods($field['methods']);
                }

                if(! empty($field['field'])) {
                    $columnStructure->setFieldClass($field['field']);
                }

                $codeStructure->addColumn($columnStructure);
            }

            if(isset($resource['timestamps']) && $resource['timestamps'] === true) {
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

            if(isset($resource['soft_deletes']) && $resource['soft_deletes'] === true) {
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

        }

        return $codeStructures;
    }
}