<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Tests\Feature;

use DevLnk\MoonShineBuilder\Enums\SqlTypeMap;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;
use DevLnk\MoonShineBuilder\Services\CodeStructure\Factories\StructureFromModel;
use DevLnk\MoonShineBuilder\Tests\Fixtures\Models\Category;
use DevLnk\MoonShineBuilder\Tests\Fixtures\Models\Point;
use DevLnk\MoonShineBuilder\Tests\Fixtures\Models\Role;
use DevLnk\MoonShineBuilder\Tests\Fixtures\Models\User;
use DevLnk\MoonShineBuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StructureFromModelTest extends TestCase
{
    #[Test]
    public function it_generates_structure_for_four_models(): void
    {
        $models = [
            User::class,
            Category::class,
            Role::class,
            Point::class,
        ];

        $codeStructures = new CodeStructureList();

        foreach ($models as $modelClass) {
            $codeStructures->addCodeStructure(
                StructureFromModel::fromModel($modelClass)->makeStructure($modelClass)
            );
        }

        $structures = $codeStructures->codeStructures();

        $this->assertCount(4, $structures);

        $structureNames = array_map(
            fn ($s) => $s->entity()->ucFirst(),
            $structures
        );

        $this->assertContains('User', $structureNames);
        $this->assertContains('Category', $structureNames);
        $this->assertContains('Role', $structureNames);
        $this->assertContains('Point', $structureNames);
    }

    #[Test]
    public function it_generates_user_structure_with_three_relations(): void
    {
        $structure = StructureFromModel::fromModel(User::class)->makeStructure(User::class);

        $this->assertEquals('User', $structure->entity()->ucFirst());
        $this->assertEquals('users', $structure->table());

        // User should have BelongsTo (role), HasMany (points), BelongsToMany (categories)
        $this->assertTrue($structure->hasBelongsTo(), 'User should have BelongsTo relation');
        $this->assertTrue($structure->hasHasMany(), 'User should have HasMany relation');
        $this->assertTrue($structure->hasBelongsToMany(), 'User should have BelongsToMany relation');

        $columns = $structure->columns();
        $columnNames = array_map(fn ($c) => $c->column(), $columns);

        // Check basic columns exist
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);

        // Check relation columns
        $relationColumns = array_filter($columns, fn ($c) => $c->relation() !== null);
        $this->assertCount(3, $relationColumns, 'User should have 3 relation columns');

        // Check BelongsTo relation (role_id -> roles)
        $roleColumn = $this->findColumnByName($columns, 'role_id');
        $this->assertNotNull($roleColumn, 'role_id column should exist');
        $this->assertEquals(SqlTypeMap::BELONGS_TO, $roleColumn->type());
        $this->assertNotNull($roleColumn->relation());
        $this->assertEquals('roles', $roleColumn->relation()->table()->raw());

        // Check HasMany relation (points)
        $pointsColumn = $this->findColumnByName($columns, 'points');
        $this->assertNotNull($pointsColumn, 'points column should exist for HasMany');
        $this->assertEquals(SqlTypeMap::HAS_MANY, $pointsColumn->type());
        $this->assertNotNull($pointsColumn->relation());
        $this->assertEquals('points', $pointsColumn->relation()->table()->raw());

        // Check BelongsToMany relation (categories)
        $categoriesColumn = $this->findColumnByName($columns, 'categories');
        $this->assertNotNull($categoriesColumn, 'categories column should exist for BelongsToMany');
        $this->assertEquals(SqlTypeMap::BELONGS_TO_MANY, $categoriesColumn->type());
        $this->assertNotNull($categoriesColumn->relation());
        $this->assertEquals('categories', $categoriesColumn->relation()->table()->raw());
    }

    #[Test]
    public function it_generates_correct_json_for_resources(): void
    {
        $models = [
            User::class,
            Category::class,
            Role::class,
            Point::class,
        ];

        $codeStructures = new CodeStructureList();

        foreach ($models as $modelClass) {
            $codeStructures->addCodeStructure(
                StructureFromModel::fromModel($modelClass)->makeStructure($modelClass)
            );
        }

        $json = $codeStructures->toJson();
        $data = json_decode($json, true);

        $this->assertArrayHasKey('resources', $data);
        $this->assertCount(4, $data['resources']);

        $resourceNames = array_column($data['resources'], 'name');
        $this->assertContains('User', $resourceNames);
        $this->assertContains('Category', $resourceNames);
        $this->assertContains('Role', $resourceNames);
        $this->assertContains('Point', $resourceNames);

        // Find User resource and check relations
        $userResource = $this->findResourceByName($data['resources'], 'User');
        $this->assertNotNull($userResource);

        $relationFields = array_filter(
            $userResource['fields'],
            fn ($f) => isset($f['relation'])
        );
        $this->assertCount(3, $relationFields, 'User resource should have 3 relation fields');

        // Check BelongsTo relation field
        $roleField = $this->findFieldByColumn($userResource['fields'], 'role_id');
        $this->assertNotNull($roleField);
        $this->assertEquals(SqlTypeMap::BELONGS_TO->value, $roleField['type']);
        $this->assertEquals('roles', $roleField['relation']['table']);

        // Check HasMany relation field
        $pointsField = $this->findFieldByColumn($userResource['fields'], 'points');
        $this->assertNotNull($pointsField);
        $this->assertEquals(SqlTypeMap::HAS_MANY->value, $pointsField['type']);
        $this->assertEquals('points', $pointsField['relation']['table']);

        // Check BelongsToMany relation field
        $categoriesField = $this->findFieldByColumn($userResource['fields'], 'categories');
        $this->assertNotNull($categoriesField);
        $this->assertEquals(SqlTypeMap::BELONGS_TO_MANY->value, $categoriesField['type']);
        $this->assertEquals('categories', $categoriesField['relation']['table']);
    }

    #[Test]
    public function it_detects_timestamps(): void
    {
        $structure = StructureFromModel::fromModel(User::class)->makeStructure(User::class);
        $this->assertTrue($structure->isTimestamps());

        $categoryStructure = StructureFromModel::fromModel(Category::class)->makeStructure(Category::class);
        $this->assertTrue($categoryStructure->isTimestamps());
    }

    #[Test]
    public function it_processes_casts_correctly(): void
    {
        $structure = StructureFromModel::fromModel(User::class)->makeStructure(User::class);
        $columns = $structure->columns();

        $isActiveColumn = $this->findColumnByName($columns, 'is_active');
        $this->assertNotNull($isActiveColumn);
        $this->assertEquals('bool', $isActiveColumn->getCast());

        $emailVerifiedColumn = $this->findColumnByName($columns, 'email_verified_at');
        $this->assertNotNull($emailVerifiedColumn);
        $this->assertEquals('datetime', $emailVerifiedColumn->getCast());
    }

    private function findColumnByName(array $columns, string $name): ?object
    {
        foreach ($columns as $column) {
            if ($column->column() === $name) {
                return $column;
            }
        }
        return null;
    }

    private function findResourceByName(array $resources, string $name): ?array
    {
        foreach ($resources as $resource) {
            if ($resource['name'] === $name) {
                return $resource;
            }
        }
        return null;
    }

    private function findFieldByColumn(array $fields, string $column): ?array
    {
        foreach ($fields as $field) {
            if ($field['column'] === $column) {
                return $field;
            }
        }
        return null;
    }
}
