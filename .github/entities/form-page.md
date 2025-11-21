## Resource example
```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
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
 * @extends FormPage<ProductResource>
 */
class ProductFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
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

    protected function formButtons(): ListOf
    {
        return parent::formButtons();
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
			'title' => ['string', 'required'],
			'content' => ['string', 'nullable'],
			'price' => ['int', 'required'],
			'sort_number' => ['int', 'required'],
			'category_id' => ['int', 'required'],
			'moonshine_user_id' => ['int', 'required'],
			'is_active' => ['boolean', 'required'],
        ];
    }

    /**
     * @param  FormBuilder  $component
     *
     * @return FormBuilder
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
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