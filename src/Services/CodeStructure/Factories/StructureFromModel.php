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
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\select;

final readonly class StructureFromModel implements MakeStructureContract
{
    /**
     * @param array<int, class-string<Model>> $modelClasses
     */
    public function __construct(
        private array $modelClasses,
    ) {
    }

    /**
     * @param class-string<Model>|null $filter
     */
    public static function make(?string $filter = null): static
    {
        $modelClasses = self::getModelSelection($filter);

        return new static($modelClasses);
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public static function fromModel(string $modelClass): static
    {
        return new static([$modelClass]);
    }

    public function makeStructures(): CodeStructureList
    {
        $codeStructureList = new CodeStructureList();

        foreach ($this->modelClasses as $modelClass) {
            $codeStructureList->addCodeStructure($this->makeStructure($modelClass));
        }

        return $codeStructureList;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public function makeStructure(string $modelClass): CodeStructure
    {
        /** @var Model $model */
        $model = new $modelClass();
        $table = $model->getTable();
        $entity = class_basename($modelClass);

        $codeStructure = new CodeStructure($table, $entity);

        $this->processColumns($model, $codeStructure);
        $this->processRelations($model, $modelClass, $codeStructure);

        return $codeStructure;
    }

    private function processColumns(Model $model, CodeStructure $codeStructure): void
    {
        $primaryKey = $model->getKeyName();
        $casts = $model->getCasts();
        $fillable = $model->getFillable();

        $idColumn = new ColumnStructure(
            column: $primaryKey,
            name: $primaryKey,
            type: SqlTypeMap::fromSqlType('primary'),
            default: null,
            nullable: false,
            required: false,
        );
        $codeStructure->addColumn($idColumn);

        foreach ($fillable as $columnName) {
            $type = $this->getTypeFromCast($casts[$columnName] ?? null);

            $columnStructure = new ColumnStructure(
                column: $columnName,
                name: $columnName,
                type: $type,
                default: null,
                nullable: true,
                required: false,
            );

            if (isset($casts[$columnName])) {
                $columnStructure->setCast($this->normalizeCast($casts[$columnName]));
            }

            $codeStructure->addColumn($columnStructure);
        }

        if ($model->usesTimestamps()) {
            $createdAt = new ColumnStructure(
                column: $model->getCreatedAtColumn(),
                name: $model->getCreatedAtColumn(),
                type: SqlTypeMap::fromSqlType('timestamp'),
                default: null,
                nullable: true,
                required: false,
            );
            $codeStructure->addColumn($createdAt);

            $updatedAt = new ColumnStructure(
                column: $model->getUpdatedAtColumn(),
                name: $model->getUpdatedAtColumn(),
                type: SqlTypeMap::fromSqlType('timestamp'),
                default: null,
                nullable: true,
                required: false,
            );
            $codeStructure->addColumn($updatedAt);
        }
    }

    private function getTypeFromCast(?string $cast): SqlTypeMap
    {
        if ($cast === null) {
            return SqlTypeMap::fromSqlType('varchar');
        }

        return match ($cast) {
            'int', 'integer' => SqlTypeMap::fromSqlType('int'),
            'real', 'float', 'double', 'decimal' => SqlTypeMap::fromSqlType('float'),
            'string' => SqlTypeMap::fromSqlType('varchar'),
            'bool', 'boolean' => SqlTypeMap::fromSqlType('boolean'),
            'array', 'json', 'object', 'collection' => SqlTypeMap::fromSqlType('json'),
            'date' => SqlTypeMap::fromSqlType('date'),
            'datetime', 'immutable_date', 'immutable_datetime', 'timestamp' => SqlTypeMap::fromSqlType('timestamp'),
            'hashed' => SqlTypeMap::fromSqlType('varchar'),
            default => SqlTypeMap::fromSqlType('varchar'),
        };
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function processRelations(Model $model, string $modelClass, CodeStructure $codeStructure): void
    {
        $reflection = new ReflectionClass($model);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->class !== $modelClass) {
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

            $columnStructure = new ColumnStructure(
                column: $foreignKey,
                name: $foreignKey,
                type: SqlTypeMap::BELONGS_TO,
                default: null,
                nullable: true,
                required: false,
            );
            $columnStructure->setRelation(new RelationStructure(
                foreignColumn: $relatedModel->getKeyName(),
                modelRelationName: $methodName,
                table: $relatedModel->getTable()
            ));

            $this->replaceColumn($codeStructure, $foreignKey, $columnStructure);
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
        $found = false;

        foreach ($columns as $key => $column) {
            if ($column->column() === $columnName) {
                $columns[$key] = $newColumn;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $columns[] = $newColumn;
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

    /**
     * @return array<int, class-string<Model>>
     */
    private static function getModelSelection(?string $filter): array
    {
        $modelsPath = app_path('Models');

        if (! File::isDirectory($modelsPath)) {
            return [];
        }

        $models = self::findModels($modelsPath);

        if (empty($models)) {
            return [];
        }

        $modelsList = collect($models)
            ->filter(fn ($class) => ! in_array(class_basename($class), self::getExcludedModels()))
            ->filter(fn ($class) => is_null($filter) || str_contains(strtolower(class_basename($class)), strtolower($filter)))
            ->mapWithKeys(fn ($class) => [$class => class_basename($class)]);

        if ($modelsList->isEmpty()) {
            return [];
        }

        $options = collect(['__all__' => 'All'])->merge($modelsList)->toArray();

        $selected = select(
            'Model',
            $options,
        );

        if ($selected === '__all__') {
            return $modelsList->keys()->toArray();
        }

        return [$selected];
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private static function findModels(string $path): array
    {
        $models = [];
        $namespace = self::getModelsNamespace($path);

        $finder = new Finder();
        $finder->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePath();
            $className = $file->getBasename('.php');

            $fullClass = $namespace;
            if ($relativePath) {
                $fullClass .= '\\' . str_replace('/', '\\', $relativePath);
            }
            $fullClass .= '\\' . $className;

            if (! class_exists($fullClass)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($fullClass);

                if (
                    $reflection->isAbstract()
                    || ! $reflection->isSubclassOf(Model::class)
                ) {
                    continue;
                }

                $models[] = $fullClass;
            } catch (\Throwable) {
                continue;
            }
        }

        return $models;
    }

    private static function getModelsNamespace(string $path): string
    {
        $appPath = app_path();

        if (str_starts_with($path, $appPath)) {
            $relativePath = substr($path, strlen($appPath));
            $relativePath = ltrim($relativePath, '/\\');

            $namespace = 'App';
            if ($relativePath) {
                $namespace .= '\\' . str_replace('/', '\\', $relativePath);
            }

            return $namespace;
        }

        return 'App\\Models';
    }

    /**
     * @return array<int, string>
     */
    private static function getExcludedModels(): array
    {
        return [
            'MoonshineUser',
            'MoonshineUserRole',
        ];
    }
}
