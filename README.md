![logo](https://github.com/moonshine-software/moonshine/raw/2.x/art/lego.png)

## Создание проектов с использованием схем для [MoonShine](https://github.com/moonshine-software/moonshine).

[![Latest Stable Version](https://img.shields.io/packagist/v/dev-lnk/moonshine-builder)](https://packagist.org/packages/dev-lnk/moonshine-builder)
[![Total Downloads](https://img.shields.io/packagist/dt/dev-lnk/moonshine-builder)](https://packagist.org/packages/dev-lnk/moonshine-builder)
[![tests](https://raw.githubusercontent.com/dev-lnk/moonshine-builder/0c267c4601af644378e1d50acc4aa4ce6bac79d6/.github/tests/badge.svg)](https://github.com/dev-lnk/moonshine-builder/actions)
[![License](https://img.shields.io/packagist/l/dev-lnk/moonshine-builder)](https://packagist.org/packages/dev-lnk/moonshine-builder)\
[![Laravel required](https://img.shields.io/badge/Laravel-10+-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP required](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php)](https://www.php.net/manual/)
[![MoonShine required](https://img.shields.io/badge/Moonshine-3.0+-1B253B?style=for-the-badge)](https://github.com/moonshine-software/moonshine)

- [Описание](#about)
- [Установка](#install)
- [Конфигурация](#config)
- [Быстрый старт](#start)
- [Методы генерации кода](#code-generate)
    - [Создание из SQL-таблицы](#sql)
    - [Создание из JSON-схемы](#json)
        - [Timestamps](#timestamps)
        - [Soft delete](#soft-delete)
        - [Флаги для генерации файлов](#flags)
    - [Генерация из openapi спецификации](#openapi)
    - [Генерация из консоли](#console)
- [Массовый импорт таблиц](#mass-sql)

---

<a name="about"></a>
## Описание

Этот пакет позволяет создавать Resource, Model и Migration со всеми полями, используя методы генерации из:

 - [SQL-таблицы](#sql),
 - [JSON-схемы](#json),
 - [Openapi (beta)](#openapi)
 - [Генерация кода для нового ресурса из консоли](#console).

Пакет генерирует следующие файлы:

 - [Resource](https://github.com/dev-lnk/moonshine-builder/blob/master/.github/entities/resource.md)
 - [Model](https://github.com/dev-lnk/moonshine-builder/blob/master/.github/entities/model.md)
 - [Migration](https://github.com/dev-lnk/moonshine-builder/blob/master/.github/entities/migration.md)


<a name="install"></a>
## Установка

```shell
composer require dev-lnk/moonshine-builder --dev
```

<a name="config"></a>
## Конфигурация

Опубликуйте файл конфигурации пакета:
```shell
php artisan vendor:publish --tag=moonshine-builder
```
В файле конфигурации укажите путь к вашим JSON-схемам:

```php
return [
    'builds_dir' => base_path('builds')
];
```

<a name="start"></a>
## Быстрый старт
Выполните команду:

```
php artisan moonshine:build
```
Вам будут предложены варианты выбора методов генерации кода, например:

```shell
 ┌ Type ────────────────────────────────────────────────────────┐
 │   ○ table                                                    │
 │ › ● json                                                     │
 │   ○ console                                                  │
 └──────────────────────────────────────────────────────────────┘
```
При выборе варианта `json`:
```shell
 ┌ File ────────────────────────────────────────────────────────┐
 │ › ● category.json                                            │
 │   ○ project.json                                             │
 └──────────────────────────────────────────────────────────────┘
```
```
app/Models/Category.php was created successfully!
app/MoonShine/Resources/CategoryResource.php was created successfully!
database/migrations/2024_05_27_140239_create_categories.php was created successfully!

WARN  Don't forget to register new resources in the provider method:

CategoryResource::class,

 ...or in the menu method:

 MenuItem::make(
     static fn() => 'CategoryResource',
      CategoryResource::class
 ),

INFO  All done.
```

Команда имеет следующую сигнатуру `moonshine:build {target?} {--type=}`, где:
 - `target` - сущность, по которой будет выполнена генерация,
 - `type` - тип или метод генерации, доступно `table`, `json`, `console`.

<a name="code-generate"></a>
## Методы генерации кода

<a name="sql"></a>
### Создание из SQL-таблицы

Вы можете создать ресурс, используя схему таблицы. Для этого выполните команду `php artisan moonshine:build` и выберите вариант `table`:
```shell
 ┌ Type ────────────────────────────────────────────────────────┐
 │ › ● table                                                    │
 │   ○ json                                                     │
 │   ○ console                                                  │
 └──────────────────────────────────────────────────────────────┘
```

Выберите необходимую таблицу:
```shell
 ┌ Table ───────────────────────────────────────────────────────┐
 │   ○ password_reset_tokens                                  │ │
 │   ○ sessions                                               │ │
 │   ○ statuses                                               │ │
 │   ○ tasks                                                  │ │
 │ › ● users                                                  ┃ │
 └──────────────────────────────────────────────────────────────┘
```

Вы можете сразу указать название таблицы и тип генерации. Пример:
```shell
php artisan moonshine:build users --type=table
```

Результат:
```php
public function indexFields(): iterable
{
    return [
        ID::make('id'),
        Text::make('name', 'name'),
        Text::make('email', 'email'),
        Date::make('email_verified_at', 'email_verified_at'),
        Text::make('password', 'password'),
        Text::make('remember_token', 'remember_token'),
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
```

<a name="json"></a>
### Создание из JSON-схемы

Структура [JSON](https://github.com/dev-lnk/moonshine-builder/blob/master/json_schema.json). В директории `builds_dir` создайте файл схемы, например, `category.json`:

```json
{
  "resources": [
    {
      "name": "Category",
      "fields": [
        {
          "column": "id",
          "type": "id",
          "methods": [
            "sortable"
          ]
        },
        {
          "column": "name",
          "type": "string",
          "name": "Name"
        }
      ]
    }
  ]
}
```

Чтобы сгенерировать файлы проекта, выполните команду:
```shell
php artisan moonshine:build category.json
```

Более подробный пример с множественными ресурсами и связями можно найти [здесь](https://github.com/dev-lnk/moonshine-builder/blob/master/examples/project.json).

<a name="timestamps"></a>
#### Timestamps

Вы можете указать флаг `timestamps: true`:
```json
{
  "resources": [
    {
      "name": "Category",
      "timestamps": true,
      "fields": []
    }
  ]
}
```
Поля `created_at` и `updated_at` будут добавлены в сгенерированный код. Если вы укажете поля `created_at` и `updated_at` вручную, флаг `timestamps` автоматически установится в `true`.

<a name="soft-delete"></a>
#### Soft delete

Работает аналогично флагу `timestamps` и полю `deleted_at`.

<a name="flags"></a>
#### Флаги для генерации файлов

С помощью флагов `withResource`, `withModel`, `withMigration` вы можете настроить, что именно требуется сгенерировать для вашего ресурса:
```json
{
  "name": "ItemPropertyPivot",
  "withResource": false,
  "withModel": false
}
```

<a name="openapi"></a>
### Генерация из Openapi схемы (yaml)
Данная функция находится в разработке, но вы уже можете сформировать данные из своей openapi спецификации в формате yaml. Для этого вам необходимо в секции path, после указания HTTP метода, указать тег <name>Resource, например:
```yaml
paths:
    /api/users:
        get:
            summary: Get all users
            operationId: listUsers
            responses:
                '200':
                    description: A list of users
        post:
            tags: [userResource]
        #...
```

<a name="console"></a>
### Генерация из консоли
Выполните команду `php artisan moonshine:build` и выберите вариант `console`, либо выполните команду `moonshine:build-resource`. Далее вам необходимо задать имя ресурса и описать все поля:

```shell
 ┌ Type ────────────────────────────────────────────────────────┐
 │ console                                                      │
 └──────────────────────────────────────────────────────────────┘

 ┌ Resource name: ──────────────────────────────────────────────┐
 │ Status                                                       │
 └──────────────────────────────────────────────────────────────┘

 ┌ Column: ─────────────────────────────────────────────────────┐
 │ id                                                           │
 └──────────────────────────────────────────────────────────────┘

 ┌ Column name: ────────────────────────────────────────────────┐
 │ Id                                                           │
 └──────────────────────────────────────────────────────────────┘

 ┌ Column type: ────────────────────────────────────────────────┐
 │ id                                                           │
 └──────────────────────────────────────────────────────────────┘

 ┌ Add more fields? ────────────────────────────────────────────┐
 │ ● Yes / ○ No                                                 │
 └──────────────────────────────────────────────────────────────┘
```

Вы можете сразу создать ресурс с полями, выполнив следующую команду:
```shell
php artisan moonshine:build-resource Status id:Id:id name:Name:string
```

Результат:
```php
public function indexFields(): iterable
{
    return [
        ID::make('id'),
        Text::make('Name', 'name'),
    ];
}
```

Сигнатура команды `moonshine:build-resource {entity?} {fields?*}`, где:
 - entity - название ресурса,
 - fields - поля для генерации вида name:Name:string или {column}:{columnName}:{type}

Все доступные {type} можно посмотреть, выполнив команду `php artisan moonshine:build-types`

<a name="mass-sql"></a>
### Массовый импорт таблиц

Если у вас уже есть проект с собственной базой данных и вы не хотите генерировать ресурсы по одному, используйте следующую команду:
```shell
php artisan moonshine:project-schema
```
Сначала выберите все ваши pivot-таблицы для корректного формирования связи BelongsToMany, затем выберите все необходимые таблицы, для которых нужно сгенерировать ресурсы:
```shell
 ┌ Select the pivot table to correctly generate BelongsToMany (Press enter to skip) ┐
 │ item_property                                                                    │
 └──────────────────────────────────────────────────────────────────────────────────┘

 ┌ Select tables ───────────────────────────────────────────────┐
 │ categories                                                   │
 │ comments                                                     │
 │ items                                                        │
 │ products                                                     │
 │ properties                                                   │
 │ users                                                        │
 └──────────────────────────────────────────────────────────────┘
```

Будет создана JSON-схема, которую при желании можно отредактировать и использовать:
```
project_20240613113014.json was created successfully! To generate resources, run: 
php artisan moonshine:build project_20240613113014.json
```