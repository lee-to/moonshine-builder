<?php

declare(strict_types=1);

namespace DevLnk\MoonShineBuilder\Services\Builders\Factory;

use DevLnk\MoonShineBuilder\Exceptions\NotFoundBuilderException;
use DevLnk\MoonShineBuilder\Services\Builders\AbstractBuilder;
use DevLnk\MoonShineBuilder\Enums\BuildType;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\DetailPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\FormPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\IndexPageBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\MigrationBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\ModelBuilderContract;
use DevLnk\MoonShineBuilder\Services\Builders\Contracts\ResourceBuilderContract;
use MoonShine\Crud\Contracts\Page\IndexPageContract;

final readonly class MoonShineBuildFactory extends AbstractBuildFactory
{
    /**
     * @throws NotFoundBuilderException
     */
    public function call(string $buildType, string $stub): void
    {
        $classParameters = [
            'codeStructure' => $this->codeStructure,
            'codePath' => $this->codePath,
            'stubFile' => $stub,
        ];

        /**
         * @var AbstractBuilder $builder
         */
        $builder = match($buildType) {
            BuildType::MODEL->value => app(
                ModelBuilderContract::class,
                $classParameters
            ),
            BuildType::RESOURCE->value => app(
                ResourceBuilderContract::class,
                $classParameters
            ),
            BuildType::MIGRATION->value => app(
                MigrationBuilderContract::class,
                $classParameters
            ),
            BuildType::INDEX_PAGE->value => app(
                IndexPageBuilderContract::class,
                $classParameters
            ),
            BuildType::FORM_PAGE->value => app(
                FormPageBuilderContract::class,
                $classParameters
            ),
            BuildType::DETAIL_PAGE->value => app(
                DetailPageBuilderContract::class,
                $classParameters
            ),
            default => throw new NotFoundBuilderException()
        };

        $builder->build();
    }
}
