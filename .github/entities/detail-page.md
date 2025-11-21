## DetailPage example
```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\Contracts\UI\FieldContract;
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
 * @extends DetailPage<ProductResource>
 */
class ProductDetailPage extends DetailPage
{
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
     * @param  TableBuilder  $component
     *
     * @return TableBuilder
     */
    protected function modifyDetailComponent(ComponentContract $component): ComponentContract
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