<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;
use DevLnk\MoonShineBuilder\Services\CodeStructure\ColumnStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\RelationStructure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;

final readonly class StructureFromModel implements MakeStructureContract
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private string $modelClass,
    ) {
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public static function make(string $modelClass): static
    {
        return new static($modelClass);
    }

    public function makeStructures(): CodeStructureList
    {
        $codeStructureList = new CodeStructureList();
        $codeStructureList->addCodeStructure($this->makeStructure());

        return $codeStructureList;
    }

    public function makeStructure(): CodeStructure
    {
        /** @var Model $model */
        $model = new $this->modelClass();
        $table = $model->getTable();
        $entity = class_basename($this->modelClass);

        $codeStructure = new CodeStructure($table, $entity);

        $this->processColumns($model, $codeStructure);
        $this->processRelations($model, $codeStructure);

        return $codeStructure;
    }

    private function processColumns(Model $model, CodeStructure $codeStructure): void
    {
        $table = $model->getTable();
        $columns = Schema::getColumns($table);
        $indexes = Schema::getIndexes($table);
        $primaryKey = $model->getKeyName();

        foreach ($indexes as $index) {
            if ($index['name'] === 'primary') {
                $primaryKey = $index['columns'][0];
                break;
            }
        }

        $casts = $model->getCasts();

        foreach ($columns as $column) {
            $type = $column['name'] === $primaryKey
                ? 'primary'
                : preg_replace("/[0-9]+|\(|\)|,/", '', $column['type']);

            if ($type === 'primary') {
                $column['default'] = null;
            }

            if (! is_null($column['default']) && str_contains($column['default'], '::')) {
                $column['default'] = substr(
                    $column['default'],
                    0,
                    strpos($column['default'], '::')
                );
            }

            $type = $column['type'] === 'tinyint(1)' ? 'boolean' : $type;

            if ($type === 'boolean' && ! is_null($column['default'])) {
                if ($column['default'] !== 'false' && $column['default'] !== true) {
                    $column['default'] = $column['default'] ? 'true' : 'false';
                }
            }

            if (
                $column['default'] === 'current_timestamp()'
                || $column['default'] === 'CURRENT_TIMESTAMP'
            ) {
                $column['default'] = '';
            }

            $sqlType = SqlTypeMap::fromSqlType($type);

            $columnStructure = new ColumnStructure(
                column: $column['name'],
                name: $column['name'],
                type: $sqlType,
                default: $column['default'],
                nullable: $column['nullable'],
                required: false,
            );

            if (isset($casts[$column['name']])) {
                $columnStructure->setCast($this->normalizeCast($casts[$column['name']]));
            }

            $codeStructure->addColumn($columnStructure);
        }
    }

    private function processRelations(Model $model, CodeStructure $codeStructure): void
    {
        $reflection = new ReflectionClass($model);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->class !== $this->modelClass) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $methodName = $method->getName();

            if (in_array($methodName, $this->getExcludedMethods())) {
                continue;
            }

            try {
                $returnType = $method->getReturnType();
                if ($returnType === null) {
                    $result = $method->invoke($model);
                } elseif ($returnType instanceof \ReflectionNamedType) {
                    $returnTypeName = $returnType->getName();
                    if (! $this->isRelationType($returnTypeName)) {
                        continue;
                    }
                    $result = $method->invoke($model);
                } else {
                    continue;
                }

                if (! is_object($result)) {
                    continue;
                }

                $this->processRelation($result, $methodName, $codeStructure);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function processRelation(mixed $relation, string $methodName, CodeStructure $codeStructure): void
    {
        if ($relation instanceof BelongsTo) {
            $relatedModel = $relation->getRelated();
            $foreignKey = $relation->getForeignKeyName();

            foreach ($codeStructure->columns() as $column) {
                if ($column->column() === $foreignKey) {
                    $column->setRelation(new RelationStructure(
                        foreignColumn: $relatedModel->getKeyName(),
                        modelRelationName: $methodName,
                        table: $relatedModel->getTable()
                    ));

                    $existingType = $column->type();
                    if ($existingType !== SqlTypeMap::BELONGS_TO) {
                        $newColumn = new ColumnStructure(
                            column: $column->column(),
                            name: $column->name(),
                            type: SqlTypeMap::BELONGS_TO,
                            default: $column->default(),
                            nullable: $column->isNullable(),
                            required: $column->isRequired(),
                        );
                        $newColumn->setRelation(new RelationStructure(
                            foreignColumn: $relatedModel->getKeyName(),
                            modelRelationName: $methodName,
                            table: $relatedModel->getTable()
                        ));

                        $this->replaceColumn($codeStructure, $foreignKey, $newColumn);
                    }
                    return;
                }
            }
        }

        if ($relation instanceof HasMany) {
            $relatedModel = $relation->getRelated();
            $foreignKey = $relation->getForeignKeyName();

            $columnStructure = new ColumnStructure(
                column: $relatedModel->getTable(),
                name: $relatedModel->getTable(),
                type: SqlTypeMap::HAS_MANY,
                default: '[]',
                nullable: false,
                required: false,
            );
            $columnStructure->setRelation(new RelationStructure(
                foreignColumn: $foreignKey,
                modelRelationName: $methodName,
                table: $relatedModel->getTable()
            ));
            $codeStructure->addColumn($columnStructure);
        }

        if ($relation instanceof HasOne) {
            $relatedModel = $relation->getRelated();
            $foreignKey = $relation->getForeignKeyName();

            $columnStructure = new ColumnStructure(
                column: str($relatedModel->getTable())->singular()->snake()->value(),
                name: str($relatedModel->getTable())->singular()->snake()->value(),
                type: SqlTypeMap::HAS_ONE,
                default: null,
                nullable: false,
                required: false,
            );
            $columnStructure->setRelation(new RelationStructure(
                foreignColumn: $foreignKey,
                modelRelationName: $methodName,
                table: $relatedModel->getTable()
            ));
            $codeStructure->addColumn($columnStructure);
        }

        if ($relation instanceof BelongsToMany) {
            $relatedModel = $relation->getRelated();
            $foreignKey = $relation->getForeignPivotKeyName();

            $columnStructure = new ColumnStructure(
                column: $relatedModel->getTable(),
                name: $relatedModel->getTable(),
                type: SqlTypeMap::BELONGS_TO_MANY,
                default: '[]',
                nullable: false,
                required: false,
            );
            $columnStructure->setRelation(new RelationStructure(
                foreignColumn: $foreignKey,
                modelRelationName: $methodName,
                table: $relatedModel->getTable()
            ));
            $codeStructure->addColumn($columnStructure);
        }
    }

    private function replaceColumn(CodeStructure $codeStructure, string $columnName, ColumnStructure $newColumn): void
    {
        $reflection = new ReflectionClass($codeStructure);
        $property = $reflection->getProperty('columns');
        $property->setAccessible(true);

        $columns = $property->getValue($codeStructure);
        foreach ($columns as $key => $column) {
            if ($column->column() === $columnName) {
                $columns[$key] = $newColumn;
                break;
            }
        }

        $property->setValue($codeStructure, $columns);

        $hasBelongsTo = $reflection->getProperty('hasBelongsTo');
        $hasBelongsTo->setAccessible(true);
        $hasBelongsTo->setValue($codeStructure, true);
    }

    private function isRelationType(string $typeName): bool
    {
        $relationTypes = [
            BelongsTo::class,
            HasMany::class,
            HasOne::class,
            BelongsToMany::class,
            \Illuminate\Database\Eloquent\Relations\Relation::class,
        ];

        foreach ($relationTypes as $relationType) {
            if ($typeName === $relationType || is_subclass_of($typeName, $relationType)) {
                return true;
            }
        }

        return false;
    }

    private function getExcludedMethods(): array
    {
        return [
            '__construct',
            '__get',
            '__set',
            '__call',
            '__callStatic',
            'getKey',
            'getKeyName',
            'getKeyType',
            'getTable',
            'getConnection',
            'getConnectionName',
            'getForeignKey',
            'getQualifiedKeyName',
            'getRouteKey',
            'getRouteKeyName',
            'resolveRouteBinding',
            'resolveSoftDeletableRouteBinding',
            'resolveChildRouteBinding',
            'query',
            'newQuery',
            'newModelQuery',
            'newQueryWithoutScopes',
            'newQueryWithoutRelationships',
            'newQueryForRestoration',
            'trashed',
            'restore',
            'forceDelete',
        ];
    }

    private function normalizeCast(mixed $cast): ?string
    {
        if (is_string($cast)) {
            return match ($cast) {
                'int', 'integer' => 'int',
                'real', 'float', 'double' => 'float',
                'string' => 'string',
                'bool', 'boolean' => 'bool',
                'array', 'json' => 'array',
                'collection' => 'collection',
                'date', 'datetime', 'immutable_date', 'immutable_datetime' => 'datetime',
                'timestamp' => 'timestamp',
                default => str_contains($cast, '\\') ? $cast : null,
            };
        }

        return null;
    }

    public function hasSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass));
    }
}
