<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;
use DevLnk\MoonShineBuilder\Services\CodeStructure\ColumnStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\RelationStructure;
use Illuminate\Support\Facades\Schema;

final readonly class StructureFromMysql implements MakeStructureContract
{
    public function __construct(
        private string $table,
        private string $entity,
        private bool $isBelongsTo = false,
        private array $hasMany = [],
        private array $hasOne = [],
        private array $belongsToMany = []
    ) {
    }

    public static function make(
        string $table,
        string $entity,
        bool $isBelongsTo = false,
        array $hasMany = [],
        array $hasOne = [],
        array $belongsToMany = []
    ): static {
        return new static($table, $entity, $isBelongsTo, $hasMany, $hasOne, $belongsToMany);
    }

    public function makeStructures(): CodeStructureList
    {
        $codeStructureList = new CodeStructureList();
        $codeStructureList->addCodeStructure($this->makeStructure());

        return $codeStructureList;
    }

    public function makeStructure(): CodeStructure
    {
        $columns = Schema::getColumns($this->table);
        $indexes = Schema::getIndexes($this->table);
        $foreignKeys = $this->isBelongsTo ? Schema::getForeignKeys($this->table) : [];

        $primaryKey = 'id';
        foreach ($indexes as $index) {
            if ($index['name'] === 'primary') {
                $primaryKey = $index['columns'][0];

                break;
            }
        }

        $foreignList = [];
        foreach ($foreignKeys as $value) {
            $foreignList[$value['columns'][0]] = [
                'table' => $value['foreign_table'],
                'foreign_column' => $value['foreign_columns'][0],
            ];
        }

        $codeStructure = new CodeStructure($this->table, $this->entity);

        foreach ($columns as $column) {
            $type = $column['name'] === $primaryKey
                ? 'primary'
                : preg_replace("/[0-9]+|\(|\)|,/", '', $column['type']);

            // For pgsql
            if ($type === 'primary') {
                $column['default'] = null;
            }

            // For pgsql
            if (! is_null($column['default']) && str_contains($column['default'], '::')) {
                $column['default'] = substr(
                    $column['default'],
                    0,
                    strpos($column['default'], '::')
                );
            }

            // For mysql
            $type = $column['type'] === 'tinyint(1)' ? 'boolean' : $type;

            if ($type === 'boolean' && ! is_null($column['default'])) {
                // For mysql
                if ($column['default'] !== 'false'
                    && $column['default'] !== true
                ) {
                    $column['default'] = $column['default'] ? 'true' : 'false';
                }
            }

            if (
                // For mysql
                $column['default'] === 'current_timestamp()'
                // For pgsql
                || $column['default'] === 'CURRENT_TIMESTAMP'
            ) {
                $column['default'] = '';
            }

            $sqlType = ($this->isBelongsTo && isset($foreignList[$column['name']]))
                ? SqlTypeMap::BELONGS_TO
                : SqlTypeMap::fromSqlType($type);

            $columnStructure = new ColumnStructure(
                column: $column['name'],
                name: $column['name'],
                type: $sqlType,
                default: $column['default'],
                nullable: $column['nullable'],
                required: false,
            );

            if ($this->isBelongsTo && isset($foreignList[$column['name']])) {
                $columnStructure->setRelation(new RelationStructure(
                    foreignColumn: $foreignList[$column['name']]['foreign_column'],
                    modelRelationName: null,
                    table: $foreignList[$column['name']]['table']
                ));
            }

            $codeStructure->addColumn($columnStructure);
        }

        foreach ($this->hasMany as $tableName) {
            $columnStructure = new ColumnStructure(
                column: $tableName,
                name: $tableName,
                type: SqlTypeMap::HAS_MANY,
                default: '[]',
                nullable: false,
                required: false,
            );
            $columnStructure->setRelation(new RelationStructure(
                foreignColumn: str($this->table)->singular()->snake()->value() . '_id',
                modelRelationName: null,
                table: $tableName
            ));
            $codeStructure->addColumn($columnStructure);
        }

        foreach ($this->hasOne as $tableName) {
            $columnStructure = new ColumnStructure(
                column: str($tableName)->singular()->snake()->value(),
                name: str($tableName)->singular()->snake()->value(),
                type: SqlTypeMap::HAS_ONE,
                default: null,
                nullable: false,
                required: false,
            );
            $columnStructure->setRelation(new RelationStructure(
                foreignColumn: str($this->table)->singular()->snake()->value() . '_id',
                modelRelationName: null,
                table: $tableName,
            ));
            $codeStructure->addColumn($columnStructure);
        }

        foreach ($this->belongsToMany as $tableName) {
            $columnStructure = new ColumnStructure(
                column: $tableName,
                name: $tableName,
                type: SqlTypeMap::BELONGS_TO_MANY,
                default: '[]',
                nullable: false,
                required: false,
            );
            $columnStructure->setRelation(new RelationStructure(
                foreignColumn: str($this->table)->singular()->snake()->value() . '_id',
                modelRelationName: null,
                table: $tableName,
            ));
            $codeStructure->addColumn($columnStructure);
        }

        return $codeStructure;
    }
}
