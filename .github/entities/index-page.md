## IndexPage example
```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\Support\ListOf;
use Throwable;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Number;
use App\MoonShine\Resources\Category\CategoryResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use App\MoonShine\Resources\Comment\CommentResource;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use MoonShine\UI\Fields\Checkbox;

/**
 * @extends IndexPage<ProductResource>
 */
class ProductIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
			ID::make('id')
				->sortable(),
			Text::make('Name', 'title'),
			Text::make('Content', 'content'),
			Number::make('Price', 'price')
				->default(0)
				->sortable(),
			Number::make('Sorting', 'sort_number')
				->default(0)
				->sortable(),
			BelongsTo::make('Category', 'category', resource: CategoryResource::class),
			HasMany::make('Comments', 'comments', resource: CommentResource::class)->creatable(),
			BelongsTo::make('User', 'moonshineUser', resource: MoonShineUserResource::class),
			Checkbox::make('Active', 'is_active'),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [];
    }

    /**
     * @return list<Metric>
     */
    protected function metrics(): array
    {
        return [];
    }

    /**
     * @param  TableBuilder  $component
     *
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer()
        ];
    }
}

```