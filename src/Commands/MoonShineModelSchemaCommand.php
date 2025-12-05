<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Commands;

use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructureList;
use DevLnk\MoonShineBuilder\Services\CodeStructure\Factories\StructureFromModel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\multiselect;

class MoonShineModelSchemaCommand extends Command
{
    protected $signature = 'moonshine:model-schema {--path= : Path to models directory}';

    protected $description = 'Generate JSON schema from Eloquent models';

    protected array $excludedModels = [
        'MoonshineUser',
        'MoonshineUserRole',
    ];

    public function handle(): int
    {
        $modelsPath = $this->option('path') ?? app_path('Models');

        if (! File::isDirectory($modelsPath)) {
            $this->error("Directory not found: $modelsPath");
            return self::FAILURE;
        }

        $models = $this->findModels($modelsPath);

        if (empty($models)) {
            $this->error('No models found in ' . $modelsPath);
            return self::FAILURE;
        }

        $modelsList = collect($models)
            ->filter(fn ($class) => ! in_array(class_basename($class), $this->excludedModels))
            ->mapWithKeys(fn ($class) => [$class => class_basename($class)]);

        if ($modelsList->isEmpty()) {
            $this->error('No eligible models found');
            return self::FAILURE;
        }

        $selectedModels = multiselect(
            'Select models to generate resources for',
            $modelsList->toArray(),
            $modelsList->keys()->toArray()
        );

        if (empty($selectedModels)) {
            $this->warn('No models selected');
            return self::SUCCESS;
        }

        $pivotModels = multiselect(
            'Select pivot models (for BelongsToMany relations). Press enter to skip',
            collect($selectedModels)->mapWithKeys(fn ($class) => [$class => class_basename($class)])->toArray(),
            []
        );

        $codeStructures = new CodeStructureList();

        foreach ($selectedModels as $modelClass) {
            $codeStructures->addCodeStructure(
                StructureFromModel::fromModel($modelClass)->makeStructure($modelClass)
            );
        }

        $dir = config('moonshine_builder.builds_dir');

        $fileSystem = new FileSystem();

        if (! $fileSystem->exists($dir)) {
            $fileSystem->makeDirectory($dir, 0777, true);
        }

        $fileName = "models_" . date('YmdHis') . ".json";

        $pivotTables = [];
        foreach ($pivotModels as $pivotModelClass) {
            /** @var Model $pivotModel */
            $pivotModel = new $pivotModelClass();
            $pivotTables[] = $pivotModel->getTable();
        }

        $fileSystem->put("$dir/$fileName", $codeStructures->toJson($pivotTables));

        $this->warn("$fileName was created successfully! To generate resources, run: ");
        $this->info("php artisan moonshine:build $fileName");

        return self::SUCCESS;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    protected function findModels(string $path): array
    {
        $models = [];
        $namespace = $this->getNamespace($path);

        $finder = new Finder();
        $finder->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePath();
            $className = $file->getBasename('.php');

            $fullClass = $namespace;
            if ($relativePath) {
                $fullClass .= '\\' . str_replace('/', '\\', $relativePath);
            }
            $fullClass .= '\\' . $className;

            if (! class_exists($fullClass)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($fullClass);

                if (
                    $reflection->isAbstract()
                    || ! $reflection->isSubclassOf(Model::class)
                ) {
                    continue;
                }

                $models[] = $fullClass;
            } catch (\Throwable) {
                continue;
            }
        }

        return $models;
    }

    protected function getNamespace(string $path): string
    {
        $appPath = app_path();

        if (str_starts_with($path, $appPath)) {
            $relativePath = substr($path, strlen($appPath));
            $relativePath = ltrim($relativePath, '/\\');

            $namespace = 'App';
            if ($relativePath) {
                $namespace .= '\\' . str_replace('/', '\\', $relativePath);
            }

            return $namespace;
        }

        return 'App\\Models';
    }
}
