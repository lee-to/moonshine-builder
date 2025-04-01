<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders;

use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Enums\StubValue;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\ModelBuilderContract;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class ModelBuilder extends AbstractBuilder implements ModelBuilderContract
{
    /**
     * @throws FileNotFoundException
     */
    public function build(): void
    {
        $modelPath = $this->codePath->path(BuildType::MODEL->value);

        $relations = $this->relationsToModel();

        StubBuilder::make($this->stubFile)
            ->setKey(
                StubValue::USE_SOFT_DELETES->key(),
                StubValue::USE_SOFT_DELETES->value(),
                $this->codeStructure->isSoftDeletes()
            )
            ->setKey(
                StubValue::SOFT_DELETES->key(),
                StubValue::SOFT_DELETES->value() . PHP_EOL,
                $this->codeStructure->isSoftDeletes()
            )
            ->setKey(
                StubValue::USE_BELONGS_TO->key(),
                StubValue::USE_BELONGS_TO->value(),
                $this->codeStructure->hasBelongsTo()
            )
            ->setKey(
                StubValue::USE_HAS_MANY->key(),
                StubValue::USE_HAS_MANY->value(),
                $this->codeStructure->hasHasMany()
            )
            ->setKey(
                StubValue::USE_HAS_ONE->key(),
                StubValue::USE_HAS_ONE->value(),
                $this->codeStructure->hasHasOne()
            )
            ->setKey(
                StubValue::USE_BELONGS_TO_MANY->key(),
                StubValue::USE_BELONGS_TO_MANY->value(),
                $this->codeStructure->hasBelongsToMany()
            )
            ->setKey(
                StubValue::RELATIONS->key(),
                $relations,
                ! empty($relations)
            )
            ->setKey(
                StubValue::TIMESTAMPS->key(),
                StubValue::TIMESTAMPS->value() . PHP_EOL,
                ! $this->codeStructure->isTimestamps()
            )
            ->setKey(
                StubValue::TABLE->key(),
                StubValue::TABLE->value() . " '{$this->codeStructure->table()}';\n",
                $this->codeStructure->table() !== $this->codeStructure->entity()->str()->plural()->snake()->value()
            )
            ->setKey(
                StubValue::CASTS->key(),
                StubValue::CASTS->value(),
                $this->getCastsCount() !== 0
            )
            ->makeFromStub($modelPath->file(), [
                '{namespace}' => $modelPath->namespace(),
                '{class}' => $this->codeStructure->entity()->ucFirstSingular(),
                '{fillable}' => $this->columnsToModel(),
                '{casts}' => $this->castsToModel(),
            ])
        ;
    }

    public function columnsToModel(): string
    {
        $result = "";

        foreach ($this->codeStructure->columns() as $column) {
            if(
                $column->type()->isIdType()
                || in_array($column->type(), $this->codeStructure->noFillableType())
                || $column->isLaravelTimestamp()
            ) {
                continue;
            }

            $result .= str("'{$column->column()}'")
                ->prepend("\t\t")
                ->prepend(PHP_EOL)
                ->append(',')
                ->value()
            ;
        }

        return $result;
    }

    public function castsToModel(): string
    {
        $result = "";

        foreach ($this->codeStructure->columns() as $column) {
            if($column->getCast() === null) {
                continue;
            }

            $result .= str("'{$column->column()}'")
                ->prepend("\t\t")
                ->prepend(PHP_EOL)
                ->append(" => ")
                ->append("'{$column->getCast()}'")
                ->append(',')
                ->value()
            ;
        }

        return $result;
    }

    /**
     * @throws FileNotFoundException
     */
    public function relationsToModel(): string
    {
        $result = str('');

        foreach ($this->codeStructure->columns() as $column) {
            if(is_null($column->relation())) {
                continue;
            }

            $stubName = match ($column->type()) {
                SqlTypeMap::BELONGS_TO => 'BelongsTo',
                SqlTypeMap::HAS_MANY => 'HasMany',
                SqlTypeMap::HAS_ONE => 'HasOne',
                SqlTypeMap::BELONGS_TO_MANY => 'BelongsToMany',
                default => ''
            };

            if(empty($stubName)) {
                continue;
            }

            $stubBuilder = StubBuilder::make($this->codeStructure->stubDir() . $stubName);
            if($column->type() === SqlTypeMap::BELONGS_TO) {
                $stubBuilder->setKey(
                    '{relation_id}',
                    ", '{$column->relation()->foreignColumn()}'",
                    $column->relation()->foreignColumn() !== 'id'
                );
            }

            $relation = $column->getModelRelationName();

            $relationColumn = ($column->type() === SqlTypeMap::HAS_MANY || $column->type() === SqlTypeMap::HAS_ONE)
                ? $column->relation()->foreignColumn()
                : $column->column();

            $relationModel = ! empty($column->getModelClass())
                ? $column->getModelClass()
                : $column->relation()->table()->str()->camel()->singular()->ucfirst()->value();

            $result = $result->newLine()->newLine()->append(
                $stubBuilder->getFromStub([
                    '{relation}' => $relation,
                    '{relation_model}' => $relationModel,
                    '{relation_column}' => $relationColumn,
                ])
            );
        }

        return $result->value();
    }

    private function getCastsCount(): int
    {
        $count = 0;
        foreach ($this->codeStructure->columns() as $column) {
            $count += $column->getCast() !== null ? 1 : 0;
        }
        return $count;
    }
}
