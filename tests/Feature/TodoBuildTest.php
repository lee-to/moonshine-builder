<?php

namespace DevLnk\MoonShineBuilder\Tests\Feature;

use DevLnk\MoonShineBuilder\Tests\TestCase;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;

class TodoBuildTest extends TestCase
{
    private string $resourcePath = '';

    private string $modelPath = '';

    private string $migrationPath = '';

    public function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();

        $this->resourcePath = config('moonshine.dir') . '/Resources/';

        $this->modelPath = app_path('Models/');

        $this->migrationPath = base_path('database/migrations/');
    }

    /**
     * @throws FileNotFoundException
     */
    #[Test]
    public function build(): void
    {
        $this->artisan('moonshine:build todo.json --type=json');

        $this->task($this->resourcePath . 'TaskResource.php', $this->modelPath . 'Task.php');
        $this->taskAttachment($this->resourcePath . 'TaskAttachmentResource.php', $this->modelPath . 'TaskAttachment.php');
//        $this->comments($this->resourcePath . 'CommentResource.php', $this->modelPath . 'Comment.php');
    }

    /**
     * @throws FileNotFoundException
     */
    private function task(string $resourcePath, string $modelPath): void
    {
        $this->assertFileExists($resourcePath);
        $this->assertFileExists($modelPath);

        $resource = $this->filesystem->get($resourcePath);
        $resourceStringContains = [
            "use MoonShine\UI\Fields\ID;",
            "use MoonShine\UI\Fields\Text;",
            "use App\Models\Task;",
            "ID::make('id')",
            "->default('Низкий')",
            "public function filters(): iterable",
        ];
        foreach ($resourceStringContains as $stringContain) {
            $this->assertStringContainsString($stringContain, $resource);
        }

        $model = $this->filesystem->get($modelPath);
        $modelContains = [
            "class Task extends Model",
            "use SoftDeletes;",
            "return \$this->hasMany(TaskAttachment::class, 'task_id');",
        ];
        foreach ($modelContains as $stringContain) {
            $this->assertStringContainsString($stringContain, $model);
        }

        $migrationFile = $this->getMigrationFile('create_tasks');
        $this->assertNotEmpty($migrationFile);
        $migration = $this->filesystem->get($migrationFile);
        $migrationContains = [
            "Schema::create('tasks', function (Blueprint \$table) {",
            "\$table->string('priority')->default('Низкий');",
        ];
        foreach ($migrationContains as $stringContain) {
            $this->assertStringContainsString($stringContain, $migration);
        }
    }

    /**
     * @throws FileNotFoundException
     */
    private function taskAttachment(string $resourcePath, string $modelPath): void
    {
        $this->assertFileExists($resourcePath);
        $this->assertFileExists($modelPath);

        $resource = $this->filesystem->get($resourcePath);
        $resourceStringContains = [
            "BelongsTo::make('Задача', 'task', resource: TaskResource::class),",
            "File::make('Файл', 'attachment')",
            "->multiple(),",
            "'attachment' => ['array', 'nullable'],",
        ];
        foreach ($resourceStringContains as $stringContain) {
            $this->assertStringContainsString($stringContain, $resource);
        }

        $model = $this->filesystem->get($modelPath);
        $modelContains = [
            "class TaskAttachment extends Model",
            "'attachment' => 'json',",
        ];
        foreach ($modelContains as $stringContain) {
            $this->assertStringContainsString($stringContain, $model);
        }

        $migrationFile = $this->getMigrationFile('create_task_attachments');
        $this->assertNotEmpty($migrationFile);
        $migration = $this->filesystem->get($migrationFile);
        $migrationContains = [
            "\$table->string('attachment');",
        ];
        foreach ($migrationContains as $stringContain) {
            $this->assertStringContainsString($stringContain, $migration);
        }
    }

    private function getMigrationFile(string $migrationName): string
    {
        $migrationFile = '';
        $migrations = $this->filesystem->allFiles($this->migrationPath);
        foreach ($migrations as $migration) {
            if(str_contains($migration, $migrationName)) {
                $migrationFile = $migration;

                break;
            }
        }

        return $migrationFile;
    }

    public function tearDown(): void
    {
        $this->filesystem->delete($this->resourcePath . 'TaskResource.php');
        $this->filesystem->delete($this->resourcePath . 'TaskAttachmentResource.php');
        $this->filesystem->delete($this->resourcePath . 'TagResource.php');
        $this->filesystem->delete($this->resourcePath . 'StageResource.php');

        $this->filesystem->delete($this->modelPath . 'Task.php');
        $this->filesystem->delete($this->modelPath . 'TaskAttachment.php');
        $this->filesystem->delete($this->modelPath . 'Tag.php');
        $this->filesystem->delete($this->modelPath . 'Stage.php');
        $this->filesystem->delete($this->modelPath . 'TaskTagPivot.php');

        $migrations = $this->filesystem->allFiles($this->migrationPath);
        foreach ($migrations as $migrationFile) {
            $this->filesystem->delete($migrationFile);
        }

        parent::tearDown();
    }
}
