<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Commands;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use Illuminate\Console\Command;

class TypeCommand extends Command
{
    protected $signature = 'moonshine:build-types';

    public function handle(): void
    {
        $types = array_map(static fn ($value) => $value->value, SqlTypeMap::cases());

        $this->components->bulletList($types);
    }
}
