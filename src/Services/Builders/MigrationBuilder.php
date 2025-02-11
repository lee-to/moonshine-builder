<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Services\CodeStructure\ColumnStructure;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\MigrationBuilderContract;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class MigrationBuilder extends AbstractBuilder implements MigrationBuilderContract
{
    /**
     * @throws FileNotFoundException
     */
    public function build(): void
    {
        $migrationPath = $this->codePath->path(BuildType::MIGRATION->value);

        StubBuilder::make($this->stubFile)
            ->setKey(
                '{timestamps}',
                PHP_EOL . "\t\t\t\$table->timestamps();",
                $this->codeStructure->isTimestamps()
            )
            ->setKey(
                '{soft_deletes}',
                PHP_EOL . "\t\t\t\$table->softDeletes();",
                $this->codeStructure->isSoftDeletes()
            )
            ->makeFromStub($migrationPath->file(), [
                '{table}' => $this->codeStructure->table(),
                '{columns}' => $this->columnsToMigration(),
            ]);
    }

    protected function columnsToMigration(): string
    {
        $result = "";

        foreach ($this->codeStructure->columns() as $column) {
            if(
                $column->type() === SqlTypeMap::HAS_ONE
                || $column->type() === SqlTypeMap::HAS_MANY
                || $column->type() === SqlTypeMap::BELONGS_TO_MANY
            ) {
                continue;
            }

            if($this->codeStructure->isTimestamps()
                && ($column->isCreatedAt() || $column->isUpdatedAt())
            ) {
                continue;
            }

            if($this->codeStructure->isSoftDeletes() && $column->isDeletedAt()) {
                continue;
            }

            $result .= str('$table->')
                ->prepend("\t\t\t")
                ->prepend("\n")
                ->append($this->migrationName($column))
                ->append($this->migrationMethods($column))
                ->append(';')
                ->value()
            ;
        }

        return $result;
    }

    protected function migrationName(ColumnStructure $column): string
    {
        if($column->relation()) {
            return $this->migrationNameFromRelation($column);
        }

        return str($column->type()->value)
            ->when(
                $column->column() === 'id' && $column->type()->value === 'id',
                fn ($str) => $str->append("("),
                fn ($str) => $str->append("('{$column->column()}'")
            )
            ->when(
                $column->getMigrationOptions() !== [],
                fn ($str) => $str->append(', ' . implode(', ', $column->getMigrationOptions()) . ')'),
                fn ($str) => $str->append(")")
            )
            ->value()
        ;
    }

    public function migrationNameFromRelation(ColumnStructure $column): string
    {
        if($column->type() !== SqlTypeMap::BELONGS_TO) {
            return '';
        }

        $modelName = str($column->relation()->table()->singular())->ucfirst()->value();

        $modelClass = empty($column->getModelClass()) ? '\\App\\Models\\' : $column->getModelClass();

        return str('foreignIdFor')
            ->append('(')
            ->append($modelClass)
            ->when(
                empty($column->getModelClass()),
                fn ($str) => $str->append($modelName)
            )
            ->append("::class")
            ->append(')')
            ->newLine()
            ->append("\t\t\t\t")
            ->append('->constrained()')
            ->newLine()
            ->append("\t\t\t\t")
            ->append('->cascadeOnDelete()')
            ->newLine()
            ->append("\t\t\t\t")
            ->append('->cascadeOnUpdate()')
            ->value()
        ;
    }

    protected function migrationMethods(ColumnStructure $column): string
    {
        if($column->getMigrationMethods() === []) {
            return '';
        }

        $result = "";

        foreach ($column->getMigrationMethods() as $method) {
            if(! str_contains($method, '(')) {
                $method .= "()";
            }
            $result .= "->$method";

        }

        return $result;
    }
}
