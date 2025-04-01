<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Support\TypeMap;

final readonly class MoonShineStructure
{
    private TypeMap $fieldMap;

    public function __construct(
        private CodeStructure $codeStructure
    ) {
        $this->fieldMap = new TypeMap();
    }

    /**
     * @throws ProjectBuilderException
     * @return array<int, string>
     */
    public function getUsesForFields(): array
    {
        $uses = [];

        foreach ($this->codeStructure->columns() as $column) {
            if($column->isLaravelTimestamp()) {
                continue;
            }

            $fieldClass = $column->getFieldClass()
                ? $this->fieldMap->fieldClassFromAlias($column->getFieldClass())
                : $this->fieldMap->getMoonShineFieldFromSqlType($column->type())
            ;

            $use = str($fieldClass)
                ->prepend('use ')
                ->append(';')
                ->value()
            ;

            if(in_array($use, $uses)) {
                continue;
            }

            $uses[] = $use;
        }

        return $uses;
    }

    /**
     * @throws ProjectBuilderException
     * @return array<int, string>
     */
    public function getFields(int $tabulation = 0, bool $onlyFilters = false): array
    {
        $fields = [];

        foreach ($this->codeStructure->columns() as $column) {
            if($column->isLaravelTimestamp()) {
                continue;
            }

            if($onlyFilters && ! $column->hasFilter()) {
                continue;
            }

            $fieldClass = $column->getFieldClass()
                ? $this->fieldMap->fieldClassFromAlias($column->getFieldClass())
                : $this->fieldMap->getMoonShineFieldFromSqlType($column->type())
            ;

            if(! is_null($column->relation())) {
                $resourceName = str($column->relation()->table()->camel())->singular()->ucfirst()->value();

                if(str_contains($resourceName, 'Moonshine')) {
                    $resourceName = str_replace('Moonshine', 'MoonShine', $resourceName);
                }

                $relationMethod = $column->getModelRelationName();

                $fields[] = str(class_basename($fieldClass))
                    ->append('::make')
                    ->append("('{$column->name()}', '$relationMethod'")
                    ->append(", resource: ")
                    ->when(
                        $column->getResourceClass(),
                        fn ($str) => $str->append($column->getResourceClass() . '::class'),
                        fn ($str) => $str->append(str($resourceName)->append('Resource')->append('::class')->value()),
                    )
                    ->append(')')
                    ->append($this->resourceMethods($column))
                    ->value();

                continue;
            }

            $fields[] = str(class_basename($fieldClass))
                ->append('::make')
                ->when(
                    ! $column->isId(),
                    fn ($str) => $str->append("('{$column->name()}', '{$column->column()}')"),
                    fn ($str) => $str->append("('{$column->column()}')"),
                )
                ->append($this->resourceMethods($column, $tabulation))
                ->value()
            ;
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public function getRules(): array
    {
        $rules = [];

        foreach ($this->codeStructure->columns() as $column) {
            if($column->isId()) {
                continue;
            }

            if(
                in_array($column->column(), $this->codeStructure->dateColumns())
                || in_array($column->type(), $this->codeStructure->noInputType())
            ) {
                continue;
            }

            $requiredValue = $column->isRequired() ? 'required' : 'nullable';

            $rules[] = str("'{$column->column()}' => ['{$column->rulesType()}'")
                ->append(", '$requiredValue']")
                ->value()
            ;
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    public function getWithProperty(): array
    {
        $withArray = [];
        foreach ($this->codeStructure->columns() as $column) {
            if(! $column->relation()) {
                continue;
            }
            $withArray[] = $column->getModelRelationName();
        }

        return $withArray;
    }

    private function resourceMethods(ColumnStructure $columnStructure, int $tabulation = 0): string
    {
        if($columnStructure->getResourceMethods() === []) {
            return '';
        }

        $tabStr = $tabulation ? str_repeat("\t", $tabulation) : '';

        $result = "";

        foreach ($columnStructure->getResourceMethods() as $method) {
            if(! str_contains($method, '(')) {
                $method .= "()";
            }
            $result .= str('')
                    ->when($tabulation > 0,
                        fn($str) => $str->newLine()->append($tabStr)
                    )
                    ->value() . "->$method";
        }

        return $result;
    }
}