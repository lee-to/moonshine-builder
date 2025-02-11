## Resource example
```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Number;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\UI\Fields\Checkbox;

/**
 * @extends ModelResource<Product>
 */
class ProductResource extends ModelResource
{
    // TODO model not found
	protected string $model = Product::class;

    protected string $title = 'ProductResource';

	protected array $with = ['category', 'comments', 'moonshineUser'];

    public function indexFields(): iterable
    {
        // TODO correct labels values
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
			BelongsTo::make('User', 'moonshineUser', resource: \MoonShine\Laravel\Resources\MoonShineUserResource::class),
			Checkbox::make('Active', 'is_active'),
        ];
    }

    public function formFields(): iterable
    {
        return [
            Box::make([
                ...$this->indexFields()
            ])
        ];
    }

    public function detailFields(): iterable
    {
        return [
            ...$this->indexFields()
        ];
    }

    public function rules(mixed $item): array
    {
        // TODO change it to your own rules
        return [
			'id' => ['int', 'nullable'],
			'title' => ['string', 'nullable'],
			'content' => ['string', 'nullable'],
			'price' => ['int', 'nullable'],
			'sort_number' => ['int', 'nullable'],
			'category_id' => ['int', 'nullable'],
			'moonshine_user_id' => ['int', 'nullable'],
			'is_active' => ['accepted', 'sometimes'],
        ];
    }
}
```