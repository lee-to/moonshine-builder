<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\CodeStructure\Factories;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;
use DevLnk\MoonShineBuilder\Services\CodeStructure\ColumnStructure;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

final readonly class StructureFromOpenapi implements MakeStructureContract
{
    public function __construct(
        private string $filePath
    ) {
    }

    public static function make(string $filePath): static
    {
        return new static($filePath);
    }

    /**
     * @throws ProjectBuilderException
     */
    public function makeStructures(): CodeStructureList
    {
        if (! file_exists($this->filePath)) {
            throw new ProjectBuilderException('OpenAPI file not available: ' . $this->filePath);
        }

        $root = Yaml::parseFile($this->filePath);

        if (! isset($root['paths']) || ! is_array($root['paths'])) {
            throw new ProjectBuilderException('Invalid OpenAPI specification: missing "paths".');
        }

        $components = [];
        if (isset($root['components']['schemas']) && is_array($root['components']['schemas'])) {
            $components = $root['components']['schemas'];
        }

        $resources = [];

        foreach ($root['paths'] as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $definition) {

                if (! isset($definition['tags']) || ! is_array($definition['tags'])) {
                    continue;
                }

                $foundResourceTags = array_filter(
                    $definition['tags'],
                    fn ($tag) => Str::endsWith($tag, 'Resource')
                );

                if (empty($foundResourceTags)) {
                    continue;
                }

                foreach ($foundResourceTags as $tag) {
                    $resourceName = Str::replaceLast('Resource', '', $tag);
                    $resourceKey = strtolower($resourceName);

                    if (! isset($resources[$resourceKey])) {
                        $resources[$resourceKey] = new CodeStructure(
                            table: str($resourceKey)->snake()->plural()->value(),
                            entity: ucfirst($resourceName),
                        );

                        // TODO migration and model?
                        $resources[$resourceKey]->setWithModel(false);
                        $resources[$resourceKey]->setWithMigration(false);
                    }

                    // TODO merge mod or not?
                    $codeStructure = $resources[$resourceKey];

                    if (isset($definition['parameters']) && is_array($definition['parameters'])) {
                        foreach ($definition['parameters'] as $parameter) {
                            if (! isset($parameter['name']) || ! isset($parameter['schema'])) {
                                continue;
                            }

                            $propName = (string) $parameter['name'];
                            $propType = $parameter['schema']['type'] ?? 'string';

                            $column = $this->makeColumn(
                                column: $propName,
                                propertyType: $propType
                            );
                            $codeStructure->addColumn($column);
                        }
                    }

                    if (
                        isset($definition['requestBody']['content']['application/json']['schema'])
                        && is_array($definition['requestBody']['content']['application/json']['schema'])
                    ) {
                        $schema = $definition['requestBody']['content']['application/json']['schema'];

                        if (isset($schema['$ref'])) {
                            $refPath = $schema['$ref'];
                            $columns = $this->resolveRefColumns($refPath, $components);
                            foreach ($columns as $column) {
                                $codeStructure->addColumn($column);
                            }
                        } elseif (isset($schema['properties']) && is_array($schema['properties'])) {
                            foreach ($schema['properties'] as $prop => $schemaDef) {
                                $propType  = $schemaDef['type'] ?? 'string';
                                $column = $this->makeColumn(
                                    column: $prop,
                                    propertyType: $propType
                                );
                                $codeStructure->addColumn($column);
                            }
                        }
                    }
                }
            }
        }

        $codeStructureList = new CodeStructureList();
        foreach ($resources as $codeStructure) {
            $validateId = false;
            foreach ($codeStructure->columns() as $columnStructure) {
                if($columnStructure->isId()) {
                    $validateId = true;
                }
            }
            if(! $validateId) {
                $codeStructure->addColumn(new ColumnStructure(
                        'id',
                        'id',
                        SqlTypeMap::ID,
                        default: null,
                        nullable: false,
                        required: false
                    )
                );
            }

            $codeStructureList->addCodeStructure($codeStructure);
        }

        return $codeStructureList;
    }

    private function resolveRefColumns(string $refPath, array $components): array
    {
        $split  = explode('/', $refPath);
        $schema = end($split);

        if (! isset($components[$schema]['properties'])
            || ! is_array($components[$schema]['properties'])) {
            return [];
        }

        $columns = [];
        foreach ($components[$schema]['properties'] as $prop => $schemaDef) {
            $propType = (string) ($schemaDef['type'] ?? 'string');
            $columns[] = $this->makeColumn($prop, $propType);
        }

        return $columns;
    }

    private function makeColumn(string $column, string $propertyType): ColumnStructure
    {
        $sqlTypeMap = match ($propertyType) {
            'integer' => SqlTypeMap::INTEGER,
            'boolean' => SqlTypeMap::BOOLEAN,
            'float', 'double', 'number' => SqlTypeMap::FLOAT,
            default => SqlTypeMap::STRING
        };

        if($column === 'id') {
            $sqlTypeMap = SqlTypeMap::ID;
        }

        return new ColumnStructure(
            column: $column,
            name: $column,
            type: $sqlTypeMap,
            default: null,
            nullable: false,
            required: false,
        );
    }
} 