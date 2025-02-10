<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure;

use DevLnk\MoonShineBuilder\Support\NameStr;

final class RelationStructure
{
    private NameStr $table;

    public function __construct(
        private readonly string $foreignColumn,
        string $table
    ) {
        $this->table = new NameStr($table);
    }

    public function foreignColumn(): string
    {
        return $this->foreignColumn;
    }

    public function table(): NameStr
    {
        return $this->table;
    }

    public function model(): string
    {
        return $this->table
            ->str()
            ->camel()
            ->singular()
            ->ucfirst()
            ->value();
    }
}
