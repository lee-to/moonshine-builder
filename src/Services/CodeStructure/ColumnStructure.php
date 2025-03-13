<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;

final class ColumnStructure
{
    private ?string $inputType = null;

    private ?string $fieldClass = null;

    private bool $hasFilter = false;

    private ?RelationStructure $relation = null;

    /** For relation resource*/
    private ?string $resourceClass = null;

    /** To set the name of the relationship method in the model*/
    private ?string $relationName = null;

    private array $resourceMethods = [];

    private array $migrationMethods = [];

    private array $migrationOptions = [];

    private ?string $modelClass = null;

    private ?string $cast = null;

    public function __construct(
        private readonly string $column,
        private string $name,
        private SqlTypeMap $type,
        private readonly ?string $default,
        private readonly bool $nullable
    ) {
        if(empty($this->name)) {
            $this->name = str($this->column)->camel()->ucfirst()->value();
        }

        $this->setInputType();
    }

    public function type(): SqlTypeMap
    {
        return $this->type;
    }

    public function column(): string
    {
        return $this->column;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function relation(): ?RelationStructure
    {
        return $this->relation;
    }

    public function default(): ?string
    {
        return $this->default;
    }

    public function defaultInStub(): ?string
    {
        if(! is_null($this->default) && $this->phpType() === 'string') {
            return "'" . trim($this->default, "'") . "'";
        }

        if(
            ! is_null($this->default)
            && ($this->phpType() === 'float' || $this->phpType() === 'int')
        ) {
            return trim($this->default, "'");
        }

        return $this->default;
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }

    public function setRelation(RelationStructure $relation): void
    {
        $this->relation = $relation;
    }

    public function inputType(): ?string
    {
        return $this->inputType;
    }

    public function isCreatedAt(): bool
    {
        return $this->column() === 'created_at';
    }

    public function isUpdatedAt(): bool
    {
        return $this->column() === 'updated_at';
    }

    public function isDeletedAt(): bool
    {
        return $this->column() === 'deleted_at';
    }

    public function isId(): bool
    {
        return  $this->type()->isIdType();
    }

    public function isFileType(): bool
    {
        $fileFields = ['File', 'Image'];

        return in_array($this->fieldClass, $fileFields);
    }

    public function hasMultiple(): bool
    {
        foreach ($this->getResourceMethods() as $method) {
            if(str_contains($method, 'multiple(')) {
                return true;
            }
        }
        return false;
    }

    public function isLaravelTimestamp(): bool
    {
        return $this->isCreatedAt() || $this->isUpdatedAt() || $this->isDeletedAt();
    }

    public function rulesType(): ?string
    {
        if($this->isFileType()) {
            if($this->hasMultiple()) {
                return 'array';
            }
            return 'file';
        }

        if($this->inputType === 'number') {
            return 'int';
        }

        if($this->inputType === 'text') {
            return 'string';
        }

        if($this->inputType === 'checkbox') {
            return 'accepted';
        }

        return $this->inputType;
    }

    public function phpType(): ?string
    {
        if(
            $this->type() === SqlTypeMap::HAS_MANY
            || $this->type() === SqlTypeMap::BELONGS_TO_MANY
        ) {
            return 'array';
        }

        if($this->type() === SqlTypeMap::HAS_ONE) {
            return $this->relation()?->table()->ucFirstSingular() . 'DTO';
        }

        if(
            $this->inputType === 'text'
            || $this->inputType === 'email'
            || $this->inputType === 'password'
        ) {
            return 'string';
        }

        if($this->type() === SqlTypeMap::BOOLEAN) {
            return 'bool';
        }

        if(
            $this->type() === SqlTypeMap::DECIMAL
            || $this->type() === SqlTypeMap::DOUBLE
            || $this->type() === SqlTypeMap::FLOAT
        ) {
            return 'float';
        }

        if($this->inputType === 'number') {
            return 'int';
        }

        return $this->inputType;
    }

    public function setInputType(): void
    {
        if(! is_null($this->inputType)) {
            return;
        }

        if($this->column === 'email' || $this->column === 'password') {
            $this->inputType = $this->column;

            return;
        }

        $this->inputType = $this->type()->getInputType();
    }

    public function getFieldClass(): ?string
    {
        return $this->fieldClass;
    }

    public function setFieldClass(?string $fieldClass): void
    {
        $this->fieldClass = $fieldClass;

        // set json cast
        if($this->cast === null && $this->isFileType() && $this->hasMultiple()) {
            $this->setCast('json');
        }
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): void
    {
        $this->resourceClass = $resourceClass;
    }

    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    public function setModelClass(?string $modelClass): void
    {
        $this->modelClass = $modelClass;
    }

    public function getResourceMethods(): array
    {
        return $this->resourceMethods;
    }

    public function setResourceMethods(array $resourceMethods): void
    {
        $this->resourceMethods = $resourceMethods;
    }

    public function getMigrationMethods(): array
    {
        return $this->migrationMethods;
    }

    public function setMigrationMethods(array $migrationMethods): void
    {
        $this->migrationMethods = $migrationMethods;
    }

    public function getMigrationOptions(): array
    {
        return $this->migrationOptions;
    }

    public function setMigrationOptions(array $migrationOptions): void
    {
        $this->migrationOptions = $migrationOptions;
    }

    public function getRelationName(): ?string
    {
        return $this->relationName;
    }

    public function setRelationName(?string $relationName): void
    {
        $this->relationName = $relationName;
    }

    public function getCast(): ?string
    {
        return $this->cast;
    }

    public function setCast(?string $cast): void
    {
        $this->cast = $cast;
    }

    public function hasFilter(): bool
    {
        return $this->hasFilter;
    }

    public function setHasFilter(bool $hasFilter): void
    {
        $this->hasFilter = $hasFilter;
    }
}
