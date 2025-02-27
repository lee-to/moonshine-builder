<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Commands;

use DevLnk\MoonShineBuilder\Enums\BuildTypeContract;
use DevLnk\MoonShineBuilder\Enums\ParseType;
use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Exceptions\CodeGenerateCommandException;
use DevLnk\MoonShineBuilder\Exceptions\NotFoundBuilderException;
use DevLnk\MoonShineBuilder\Exceptions\ProjectBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\Factory\MoonShineBuildFactory;
use DevLnk\MoonShineBuilder\Services\CodePath\CodePathContract;
use DevLnk\MoonShineBuilder\Services\CodePath\MoonShineCodePath;
use DevLnk\MoonShineBuilder\Services\CodeStructure\CodeStructure;
use DevLnk\MoonShineBuilder\Services\CodeStructure\Factories\MoonShineStructureFactory;
use DevLnk\MoonShineBuilder\Services\StubBuilder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
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

    /** @var array<array-key, BuildType> */
    protected array $builders = [];

    /** @var array<string, string> */
    protected array $replaceCautions = [];

    protected ParseType $parseType;
    /**
     * @throws CodeGenerateCommandException
     * @throws FileNotFoundException
     * @throws NotFoundBuilderException
     * @throws ProjectBuilderException
     */
    public function handle(): int
    {
        $target = $this->argument('target');

        $this->parseType = ParseType::from($this->getType($target));

        if($this->parseType === ParseType::CONSOLE) {
            $this->call('moonshine:build-resource');
            return self::SUCCESS;
        }

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
     * @throws NotFoundBuilderException
     */
    protected function buildCode(CodeStructure $codeStructure, CodePathContract $codePath): void
    {
        $buildFactory = new MoonShineBuildFactory(
            $codeStructure,
            $codePath
        );

        $validBuilders = array_filter([
            $codeStructure->withModel() ? BuildType::MODEL : null,
            $codeStructure->withMigration() ? BuildType::MIGRATION : null, 
            $codeStructure->withResource() ? BuildType::RESOURCE : null
        ]);

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

        if(! in_array(BuildType::RESOURCE, $this->builders)) {
            return;
        }

        $resourcePath = $codePath->path(BuildType::RESOURCE->value);

        $this->reminderResourceInfo[] = "{$resourcePath->rawName()}::class,";
        $this->reminderMenuInfo[] = StubBuilder::make($this->stubDir . 'MenuItem')
            ->getFromStub([
                '{resource}' => $resourcePath->rawName(),
            ])
        ;
    }

    /**
     * @return array<int, CodeStructure>
     * @throws ProjectBuilderException
     */
    protected function codeStructures(): array
    {
        $target = $this->argument('target');

        if (is_null($target) && $this->parseType === ParseType::JSON) {
            $target = $this->getFileList('json');
        }
        
        if (is_null($target) && $this->parseType === ParseType::OPENAPI) {
            $target = $this->getFileList('yaml');
        }

        if($this->parseType === ParseType::TABLE) {
            $target = select(
                'Table',
                collect(Schema::getTables())
                    ->filter(fn ($v) => str_contains((string) $v['name'], (string) $target ?? ''))
                    ->mapWithKeys(fn ($v) => [$v['name'] => $v['name']]),
                default: 'jobs'
            );

            $this->builders = array_filter($this->builders, fn ($item) => $item !== BuildType::MIGRATION);
        }

        $codeStructures = (new MoonShineStructureFactory())->getStructures($this->parseType, $target);

        return $codeStructures->makeStructures()->codeStructures();
    }

    protected function getFileList(string $extension): int|string
    {
        /** @var Collection<array-key, string> $files */
        $files = collect(File::files(config('moonshine_builder.builds_dir')))->mapWithKeys(
            static function (SplFileInfo $file) use ($extension): array {
                if(! str_contains($file->getFilename(), '.' . $extension)) {
                    return [];
                }
                return [
                    $file->getFilename() => $file->getFilename(),
                ];
            }
        );

        return select(
            'File',
            $files,
        );
    }

    /**
     * @throws CodeGenerateCommandException
     * @throws FileNotFoundException
     * @throws NotFoundBuilderException
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
                ParseType::JSON->value,
            ];

            $fileSeparate = explode('.', $target);
            $type = $fileSeparate[count($fileSeparate) - 1];

            if (in_array($type, $availableTypes)) {
                return $type;
            }
        }

        $typeList = [];
        foreach (ParseType::cases() as $parseType) {
            $typeList[$parseType->value] = $parseType->toString();
        }

        return $this->option('type') ?? select(
            'Type',
            $typeList
        );
    }

    protected function codePath(): CodePathContract
    {
        $codePath = new MoonShineCodePath($this->iterations);
        $this->iterations++;

        return $codePath;
    }

    protected function resourceInfo(): void
    {
        if(! in_array(BuildType::RESOURCE, $this->builders)) {
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
            BuildType::MODEL,
            BuildType::RESOURCE,
            BuildType::MIGRATION,
        ];
    }
}
