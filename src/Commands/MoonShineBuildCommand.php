<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Commands;

use DevLnk\MoonShineBuilder\Enums\BuildTypeContract;
use DevLnk\MoonShineBuilder\Enums\MoonShineBuildType;
use DevLnk\MoonShineBuilder\Exceptions\CodeGenerateCommandException;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Factory\AbstractBuildFactory;
use DevLnk\MoonShineBuilder\Services\Builders\Factory\MoonShineBuildFactory;
use DevLnk\MoonShineBuilder\Services\CodePath\CodePathContract;
use DevLnk\MoonShineBuilder\Services\CodePath\MoonShineCodePath;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use DevLnk\MoonShineBuilder\Structures\Factories\StructureFromMysql;
use DevLnk\MoonShineBuilder\Structures\Factories\MoonShineStructureFactory;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use SplFileInfo;

use function Laravel\Prompts\{confirm, note, select};

class MoonShineBuildCommand extends Command
{
    protected $signature = 'moonshine:build {target?} {--type=}';

    protected int $iterations = 0;

    protected ?string $stubDir = '';

    /** @var array<int, string> */
    protected array $reminderResourceInfo = [];

    /** @var array<int, string> */
    protected array $reminderMenuInfo = [];

    /** @var array<array-key, MoonShineBuildType> */
    protected array $builders = [];

    /** @var array<string, string> */
    protected array $replaceCautions = [];

    /**
     * @throws CodeGenerateCommandException
     * @throws ProjectBuilderException
     */
    public function handle(): int
    {
        $this->setStubDir();

        $this->prepareBuilders();

        $codeStructures = $this->codeStructures();

        $generationPath = $this->generationPath();

        foreach ($codeStructures as $codeStructure) {
            $this->make($codeStructure, $generationPath);
        }

        $this->resourceInfo();

        $this->components->info('All done');

        return self::SUCCESS;
    }

    /**
     * @throws CodeGenerateCommandException
     * @throws FileNotFoundException
     */
    protected function buildCode(CodeStructure $codeStructure, CodePathContract $codePath): void
    {
        $buildFactory = $this->buildFactory(
            $codeStructure,
            $codePath,
        );

        $validBuildMap = [
            'withModel' => MoonShineBuildType::MODEL,
            'withMigration' => MoonShineBuildType::MIGRATION,
            'withResource' => MoonShineBuildType::RESOURCE,
        ];

        $validBuilders = [];
        foreach ($validBuildMap as $dataKey => $builder) {
            if(
                ! is_null($codeStructure->dataValue($dataKey))
                && $codeStructure->dataValue($dataKey) === false
            ) {
                continue;
            }
            $validBuilders[] = $builder;
        }

        foreach ($this->builders as $builder) {
            if(! $builder instanceof BuildTypeContract) {
                throw new CodeGenerateCommandException('builder is not BuildTypeContract');
            }

            if(! in_array($builder, $validBuilders)) {
                continue;
            }

            $confirmed = true;
            if(isset($this->replaceCautions[$builder->value()])) {
                $confirmed = confirm($this->replaceCautions[$builder->value()]);
            }

            if(! $confirmed) {
                continue;
            }

            $buildFactory->call($builder->value(), $this->stubDir . $builder->stub());
            $filePath = $codePath->path($builder->value())->file();
            $this->info($this->projectFileName($filePath) . ' was created successfully!');
        }

        if(! in_array(MoonShineBuildType::RESOURCE, $this->builders)) {
            return;
        }

        $resourcePath = $codePath->path(MoonShineBuildType::RESOURCE->value);

        $this->reminderResourceInfo[] = "{$resourcePath->rawName()}::class,";
        $this->reminderMenuInfo[] = StubBuilder::make($this->stubDir . 'MenuItem')
            ->getFromStub([
                '{resource}' => $resourcePath->rawName(),
            ])
        ;
    }

    /**
     * @return array<int, CodeStructure>
     *
     * @throws ProjectBuilderException
     */
    protected function codeStructures(): array
    {
        $target = $this->argument('target');

        $type = $this->getType($target);

        if (is_null($target) && $type === 'json') {
            $target = select(
                'File',
                collect(File::files(config('moonshine_builder.builds_dir')))->mapWithKeys(
                    fn (SplFileInfo $file): array => [
                        $file->getFilename() => $file->getFilename(),
                    ]
                ),
            );
        }

        // If it is a sql table, the standard parent package generation is used
        if($type === 'table') {
            $target = select(
                'Table',
                collect(Schema::getTables())
                    ->filter(fn ($v) => str_contains((string) $v['name'], (string) $target ?? ''))
                    ->mapWithKeys(fn ($v) => [$v['name'] => $v['name']]),
                default: 'jobs'
            );

            $this->builders = array_filter($this->builders, fn ($item) => $item !== MoonShineBuildType::MIGRATION);

            return [
                StructureFromMysql::make(
                    table: (string) $target,
                    entity: $target,
                    isBelongsTo: true,
                    hasMany: [],
                    hasOne: [],
                    belongsToMany: []
                ),
            ];
        }

        $codeStructures = (new MoonShineStructureFactory())->getStructures($target);

        return $codeStructures->codeStructures();
    }

    /**
     * @throws CodeGenerateCommandException
     */
    protected function make(CodeStructure $codeStructure, string $generationPath): void
    {
        $codeStructure->setStubDir($this->stubDir);

        $codePath = $this->codePath();

        $this->prepareGeneration($generationPath, $codeStructure, $codePath);

        $this->buildCode($codeStructure, $codePath);
    }

    protected function prepareGeneration(string $generationPath, CodeStructure $codeStructure, CodePathContract $codePath): void
    {
        $isGenerationDir = $generationPath !== '_default';

        $fileSystem = new Filesystem();

        if($isGenerationDir) {
            $genPath = base_path($generationPath);
            if(! $fileSystem->isDirectory($genPath)) {
                $fileSystem->makeDirectory($genPath, recursive: true);
                $fileSystem->put($genPath . '/.gitignore', "*\n!.gitignore");
            }
        }

        $codePath->initPaths($codeStructure, $generationPath, $isGenerationDir);

        if(! $isGenerationDir) {
            foreach ($this->builders as $buildType) {
                if($fileSystem->isFile($codePath->path($buildType->value())->file())) {
                    $this->replaceCautions[$buildType->value()] =
                        $this->projectFileName($codePath->path($buildType->value())->file()) . " already exists, are you sure you want to replace it?";
                }
            }
        }
    }

    protected function projectFileName(string $filePath): string
    {
        if(str_contains($filePath, '/resources/views')) {
            return substr($filePath, strpos($filePath, '/resources/views') + 1);
        }

        if(str_contains($filePath, '/routes')) {
            return substr($filePath, strpos($filePath, '/routes') + 1);
        }

        return substr($filePath, strpos($filePath, '/app') + 1);
    }

    protected function getType(?string $target): string
    {
        if (! $this->option('type') && ! is_null($target)) {
            $availableTypes = [
                'json',
            ];

            $fileSeparate = explode('.', $target);
            $type = $fileSeparate[count($fileSeparate) - 1];

            if (in_array($type, $availableTypes)) {
                return $type;
            }
        }

        return $this->option('type') ?? select('Type', ['json', 'table']);
    }

    protected function codePath(): CodePathContract
    {
        $codePath = new MoonShineCodePath($this->iterations);
        $this->iterations++;

        return $codePath;
    }

    protected function resourceInfo(): void
    {
        if(! in_array(MoonShineBuildType::RESOURCE, $this->builders)) {
            return;
        }

        $this->components->warn(
            "Don't forget to register new resources in the provider method:"
        );
        $code = implode(PHP_EOL, $this->reminderResourceInfo);
        note($code);

        note("...and in the menu");

        $code = implode(PHP_EOL, $this->reminderMenuInfo);
        note($code);
    }

    protected function buildFactory(
        CodeStructure $codeStructure,
        CodePathContract $codePath
    ): AbstractBuildFactory {
        return new MoonShineBuildFactory(
            $codeStructure,
            $codePath
        );
    }

    public function generationPath(): string
    {
        return '_default';
    }

    protected function setStubDir(): void
    {
        $this->stubDir = __DIR__ . '/../../stubs/';
    }

    protected function prepareBuilders(): void
    {
        $this->builders = [
            MoonShineBuildType::MODEL,
            MoonShineBuildType::RESOURCE,
            MoonShineBuildType::MIGRATION,
        ];
    }
}
