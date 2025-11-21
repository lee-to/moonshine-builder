## Resource example
```php
<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product;

use App\Models\Product;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use App\MoonShine\Resources\Product\Pages\ProductDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<Product, ProductIndexPage, ProductFormPage, ProductDetailPage>
 */
class ProductResource extends ModelResource
{
    protected string $model = Product::class;

	protected array $with = ['category', 'comments', 'moonshineUser'];

    protected string $title = 'Product';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ProductIndexPage::class,
            ProductFormPage::class,
            ProductDetailPage::class,
        ];
    }
}

```