<?php

namespace DevLnk\MoonShineBuilder\Tests\Feature;

use DevLnk\MoonShineBuilder\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;

class ProjectBuildTest extends TestCase
{
    private string $resourcePath = '';

    private string $modelPath = '';

    private string $migrationPath = '';

    public function setUp(): void
    {
        parent::setUp();

        $this->resourcePath = config('moonshine.dir') . '/Resources/';

        $this->modelPath = app_path('Models/');

        $this->migrationPath = base_path('database/migrations/');
    }

    #[Test]
    public function build(): void
    {
        $this->artisan('moonshine:build project.json');

        $this->assertFileExists($this->resourcePath . 'CategoryResource.php');
        $this->assertFileExists($this->resourcePath . 'ProductResource.php');
        $this->assertFileExists($this->resourcePath . 'CommentResource.php');

        $this->assertFileExists($this->modelPath . 'Category.php');
        $this->assertFileExists($this->modelPath . 'Product.php');
        $this->assertFileExists($this->modelPath . 'Comment.php');

        $migrations = (new Filesystem())->allFiles($this->migrationPath);

        $checkMigrations = [
            'create_categories',
            'create_products',
            'create_comments',
        ];

        foreach ($checkMigrations as $checkMigration) {
            $isExists = false;
            foreach ($migrations as $migration) {
                if(str_contains($migration, $checkMigration)) {
                    $isExists = true;
                    break;
                }
            }

            $this->assertTrue($isExists, "Migration not found $checkMigration");
        }
    }
}
