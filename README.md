# Filament Page Blocks

Structured pages, reusable content blocks, menus, media, roles, users, shared site data, and generated Filament CRUD for Laravel applications.

## Requirements

- PHP 8.4+
- Laravel 13
- Filament 5
- a database supported by Laravel

Composer package: `dannapi/filament-page-blocks`
Namespace: `DannAPI\FilamentPageBlocks`

## Install

### From GitHub

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/DannAPI/filament-page-blocks"
        }
    ]
}
```

```bash
composer require dannapi/filament-page-blocks:dev-main
php artisan page-blocks:install
php artisan storage:link
```

### Local path repository

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../filament-page-blocks",
            "options": { "symlink": true }
        }
    ]
}
```

```bash
composer require dannapi/filament-page-blocks:@dev
php artisan page-blocks:install
```

The installer:

1. records `filament/filament:^5.0` as a direct root dependency;
2. publishes config, migrations, and application scaffolding;
3. configures `App\Models\User` for Filament and package roles;
4. creates or detects the actual Filament Panel Provider;
5. registers `FilamentPageBlocksPlugin` and removes `FilamentInfoWidget`;
6. publishes Filament assets and installs the Composer upgrade hook;
7. registers package seeders in `DatabaseSeeder` without duplicates;
8. asks whether to publish and run `ExamplePageSeeder`;
9. runs migrations and initial seeders.

Non-interactive installation:

```bash
php artisan page-blocks:install \
    --panel-id=admin \
    --panel-path=admin \
    --no-interaction
```

Useful installer options:

```bash
php artisan page-blocks:install --skip-migrate --skip-seed
php artisan page-blocks:install --skip-composer
php artisan page-blocks:install --without-example-page
php artisan page-blocks:install --force
```

`--force` overwrites published files. Review local changes first.

### Manual publishing

```bash
php artisan vendor:publish --tag=filament-page-blocks-config
php artisan vendor:publish --tag=filament-page-blocks-migrations
php artisan vendor:publish --tag=filament-page-blocks-views
php artisan migrate
```

## Initial admin data

Local installation creates:

- roles: `admin`, `manager`, `user`;
- users: `admin@test.com / 123`, `manager@test.com / 123`;
- empty menus: `admin`, `header`, `footer`;
- one GeneralInfo record.

Fixed demo credentials are disabled in production unless explicitly enabled. Change or disable them in `seeders.demo_users` before deployment.

Default admin URLs for an `admin` panel:

```text
/admin
/admin/pages
/admin/menus
/admin/media
/admin/general-info/1/edit
/admin/roles
/admin/users
```

### Application seeders

The installer adds only the composite package seeder to `DatabaseSeeder`:

```php
use Database\Seeders\FilamentPageBlocksSeeder;

public function run(): void
{
    $this->call([
        FilamentPageBlocksSeeder::class,
    ]);
}
```

If accepted during installation, `database/seeders/ExamplePageSeeder.php` is also published and called once from `DatabaseSeeder`. Use it as the starting point for application Page seeders. Package seeders are idempotent; run them with:

```bash
php artisan db:seed
```

## Filament plugin

The installer registers the plugin automatically. For an additional panel:

```php
use DannAPI\FilamentPageBlocks\Filament\FilamentPageBlocksPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentPageBlocksPlugin::make(),
    ]);
}
```

Register panel-specific blocks or templates:

```php
FilamentPageBlocksPlugin::make()
    ->blocks([LandingHeroBlock::class])
    ->templates([$landingTemplate]);
```

## Create a block

```bash
php artisan make:page-block Hero --view
```

Created files:

```text
app/PageBlocks/HeroBlock.php
resources/views/page-blocks/hero.blade.php
```

The command registers the block in `AppServiceProvider`. Use `--no-register` for manual registration, `--force` to overwrite.

### Block class

```php
<?php

declare(strict_types=1);

namespace App\PageBlocks;

use DannAPI\FilamentPageBlocks\Blocks\AbstractBlock;

final class HeroBlock extends AbstractBlock
{
    public static function getName(): string
    {
        return 'hero';
    }

    public static function getLabel(): string
    {
        return 'Hero';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function isReusable(): bool
    {
        return false;
    }

    public static function form(): array
    {
        return [
            self::text('title', required: true, maxLength: 160),
            self::textarea('text', maxLength: 1000, rows: 4),
            self::image('image'),
            self::video('video'),
        ];
    }

    public static function view(): string
    {
        return 'page-blocks.hero';
    }
}
```

`AbstractBlock` derives defaults from the form and supplies normalization, authorization, reuse policy, and a class-name summary. Override `defaults()`, `normalize()`, `authorize()`, `isReusable()`, or `summary()` when required.

### Field helpers

Common helpers available inside block classes:

```php
self::text(...)
self::integer(...)
self::number(...)
self::decimal(...)
self::money(...)
self::textarea(...)
self::select(...)
self::radio(...)
self::toggleButtons(...)
self::checkbox(...)
self::checkboxList(...)
self::toggle(...)
self::richText(...)
self::markdown(...)
self::code(...)
self::date(...)
self::dateTime(...)
self::time(...)
self::color(...)
self::tags(...)
self::keyValue(...)
self::slider(...)
self::hidden(...)
self::repeater(...)
self::image(...)
self::video(...)
self::file(...)
```

Every helper returns the native Filament component and remains chainable. `self::fields()` exposes the shared `BlockFields` instance when a generic/custom component is needed.

Upload validation uses `media.image_*`, `media.video_*`, and `media.file_*` config values.

Use a paired media source when an editor may either upload a file or enter an existing path/HTTPS URL:

```php
public static function form(): array
{
    return [
        ...self::imageSource('image', 'external_image', directory: 'heroes'),
        ...self::videoSource('video', 'external_video'),
        ...self::fileSource('document', 'external_document'),
    ];
}
```

The external value has rendering priority, while the uploaded value remains stored as fallback. The package never downloads remote assets server-side.

### Block relationships

Block relations store model keys in JSON and resolve models at render time:

```php
use App\Models\Author;

public static function form(): array
{
    return [
        self::belongsTo('author_id', Author::class, 'name', required: true),
        self::belongsToMany('author_ids', Author::class, 'name'),
    ];
}
```

Supported helpers include `belongsTo`, `belongsToMany`, `hasOne`, `hasMany`, through variants, `morphOne`, `morphMany`, `morphToMany`, `morphedByMany`, and `morphTo`.

### Block view variables

```blade
<section>
    <h1>{{ $data->title }}</h1>

    @if ($url = page_block_asset($data->external_image, fallback: $data->image))
        <img src="{{ $url }}" alt="{{ $data->get('title') }}">
    @endif

    @if ($author = $data->model('author_id'))
        <p>{{ $author->name }}</p>
    @endif
</section>
```

Available variables:

- `$data`: immutable `BlockData`; use property access (`$data->title`), `get()`, array access, `all()`, `model()`, `models()`, or `relation()`;
- `$page`: current Page model;
- `$block`: current PageBlock model;
- `$generalInfo`: shared singleton model or `null`.

Escape normal text with `{{ }}`. Sanitize rich HTML before `{!! !!}`; the built-in Rich Text block uses `RichTextSanitizer`.

`page_block_asset()` is the single helper for images, video, downloads, and backgrounds. It accepts an existing public path, configured-disk path, or safe HTTPS URL and otherwise returns its optional fallback or `null`:

```blade
<section style="background-image: url('{{ page_block_asset($data->background_image) }}')">
```

### Manual block registration

```php
use App\PageBlocks\HeroBlock;
use DannAPI\FilamentPageBlocks\Facades\PageBlocks;

public function boot(): void
{
    PageBlocks::register([
        HeroBlock::class,
    ]);
}
```

## Create and seed pages

Page defaults:

```php
template = 'default'
is_homepage = false
status = 'draft'
```

Only one Page may have `is_homepage=true`. A second homepage throws a validation error.

```php
use App\PageBlocks\HeroBlock;
use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Support\PageBlockSynchronizer;
use Illuminate\Database\Seeder;

final class HomePageSeeder extends Seeder
{
    public function run(PageBlockSynchronizer $synchronizer): void
    {
        $page = Page::query()->updateOrCreate(
            ['slug' => '/'],
            [
                'title' => 'Home',
                'status' => PageStatus::Published,
                'is_homepage' => true,
                'seo_title' => 'Home',
                'seo_description' => 'Homepage description',
            ],
        );

        $synchronizer->sync($page, [
            [
                'type' => HeroBlock::getName(),
                'data' => [
                    'title' => 'Welcome',
                    'text' => 'Homepage content',
                    'image' => 'page-blocks/home/hero.webp',
                ],
            ],
        ]);
    }
}
```

`sync()` marks the Page and blocks as system-managed by default. System Pages cannot be deleted or reordered in Filament. Use `systemManaged: false` for editable/removable seeded content:

```php
$synchronizer->sync($page, $blocks, systemManaged: false);
```

Statuses: `draft`, `published`, `scheduled`, `archived`. Published Pages receive `published_at` automatically. Scheduled Pages require a date.

## Create CRUD for an application model

Create and migrate the model first:

```bash
php artisan make:model Author -m
php artisan migrate
php artisan make:admin-model Author --record-title-attribute=name
```

The command reads the live database schema and generates a compact Filament Resource, policy, permission definition, and Manage page. It maps booleans, integers, decimals/money, dates, enums, JSON, conventional rich-text names, and conventional image/video/file names to `InteractsWithAdminFields`. Detected media fields are placed in the sidebar. When the table has a `sort` column, the generator adds `HasSortablePosition`, orders the index by `sort`, and enables paginated drag-and-drop. Only records visible on the current page are loaded and reordered; their global position slots are preserved, so records on other pages remain unchanged. Editing `sort` directly moves the record to that global position and shifts only the affected range. The generated policy authorizes reordering through the existing `update` permission. Review domain validation and relationships in the generated Resource. An existing Resource is never changed unless `--force` is explicitly supplied.

Options:

```bash
php artisan make:admin-model Author --panel=admin
php artisan make:admin-model Author --view
php artisan make:admin-model Author --soft-deletes
php artisan make:admin-model Author --no-policy
php artisan make:admin-model Author --force
```

Generated Resources use `InteractsWithAdminFields`:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        self::formLayout(
            main: [
                self::text('name', required: true),
                self::richText('description'),
                self::belongsTo('user_id', 'user', 'name'),
            ],
            sidebar: [
                self::image('avatar'),
            ],
        ),
    ]);
}
```

Form helpers also include `integer`, `number`, `decimal`, `money`, checkbox/radio/toggle-button choices, Markdown, code, dates, color, tags, key-value, slider, hidden, and the paired media source helpers. Table helpers: `textColumn`, `richTextColumn`, `booleanColumn`, `imageColumn`, `numericColumn`, `moneyColumn`, `badgeColumn`, `dateColumn`, and `dateTimeColumn`. Infolist helpers: `textEntry`, `imageEntry`, `booleanEntry`, `dateTimeEntry`, and `moneyEntry`.

For an existing Resource that uses the trait, the same behavior can be enabled without regenerating it:

```php
public static function table(Table $table): Table
{
    return self::reorderableTable($table)
        ->columns([/* ... */]);
}
```

The corresponding policy must expose `reorder()`; generated policies map it to the model's `update` permission.

## Frontend rendering

Package routes are enabled by default:

```text
GET /          Page with slug /
GET /{slug}    Published Page by slug
```

Missing or unpublished Pages return HTTP 404 through a Blade View. If the application contains `resources/views/errors/404.blade.php`, it is used automatically. Otherwise the controller falls back to `filament-page-blocks::errors.404`. The package fallback extends `filament-page-blocks::template`, so it receives the same configured CSS, JavaScript, header, footer, menus, GeneralInfo, and frontend assets as normal Pages. Admin, API, and configured reserved paths are excluded.

Manual controller rendering:

```php
$page = Page::query()
    ->published()
    ->where('slug', $slug)
    ->firstOrFail();

return app(\DannAPI\FilamentPageBlocks\Contracts\PageBlocksRenderer::class)
    ->render($page);
```

Page layouts receive:

```text
$page, $content, $template, $assets, $frontend, $pageName,
$isHomepage, $homeUrl, $headerMenu, $footerMenu,
$generalInfo, $navigation, $site
```

Publish views to customize the frontend shell:

```bash
php artisan vendor:publish --tag=filament-page-blocks-views
```

Important files:

```text
resources/views/vendor/filament-page-blocks/template.blade.php
resources/views/vendor/filament-page-blocks/parts/header.blade.php
resources/views/vendor/filament-page-blocks/parts/footer.blade.php
resources/views/vendor/filament-page-blocks/pages/default.blade.php
resources/views/vendor/filament-page-blocks/errors/404.blade.php
```

For one application-wide frontend error page, create `resources/views/errors/404.blade.php`; it takes priority without publishing package views. The View receives `$slug`, `$exception`, `$page`, `$template`, `$assets`, `$frontend`, `$homeUrl`, `$headerMenu`, `$footerMenu`, `$generalInfo`, `$navigation`, and `$site`. To reuse the package shell, start it with `@extends('filament-page-blocks::template')`. Use the vendor path above only when you want to customize the package fallback.

Place site CSS, JS, fonts, and design images in `public/`. Configure URLs under `frontend.assets`.

## Page templates

Config definition:

```php
'templates' => [
    'landing' => [
        'label' => 'Landing page',
        'blocks' => [HeroBlock::class, RichTextBlock::class],
        'layout' => 'pages.landing',
    ],
],
```

Runtime definition:

```php
use DannAPI\FilamentPageBlocks\Data\PageTemplate;
use DannAPI\FilamentPageBlocks\Facades\PageBlocks;

PageBlocks::templates([
    PageTemplate::make('landing')
        ->label('Landing page')
        ->blocks([HeroBlock::class, RichTextBlock::class])
        ->layout('pages.landing'),
]);
```

When only `default` exists, the Template field is hidden.

## GeneralInfo

Edit shared header/footer/site values at `/admin/general-info/1/edit`.

```blade
{{ $generalInfo?->value('phone') }}
{{ $generalInfo?->value('email') }}

@if ($logo = $generalInfo?->imageUrl('logo'))
    <img src="{{ $logo }}" alt="Logo">
@endif

{{ $generalInfo?->richText('footer_about') }}
```

The record supports key/value data, named images, and sanitized named rich text.

## Menus and admin navigation

Create menu items at `/admin/menus`.

- `header` and `footer` are available in frontend views as `$headerMenu` and `$footerMenu`.
- `admin` controls Filament sidebar visibility, labels, order, custom links, children, and Heroicons.
- Resources and Pages are synchronized into the admin menu automatically.
- Native Filament authorization is still applied.

Render a frontend menu:

```blade
@include('filament-page-blocks::components.menu', [
    'items' => $headerMenu?->items ?? collect(),
])
```

## Media

`/admin/media` contains:

- read-only system assets;
- writable admin uploads;
- image and video preview;
- folders, uploads, and deletion permissions.

Defaults:

```dotenv
PAGE_BLOCKS_DISK=public
```

Configure disk, directories, MIME types, and size limits under `media`. `media.asset_urls` controls whether safe external HTTPS sources are allowed, accepted HTTPS ports, and the image/video/file extensions accepted by `page_block_asset()`. Run `php artisan storage:link` for the public disk. File paths are stored in JSON; files remain on the configured filesystem. Remote assets are referenced in frontend markup and are never downloaded by the package.

The `image()`, `video()`, and `file()` helpers show edit-form previews for both normal files on the configured Laravel disk and existing source-controlled files under `public/`, such as `img/demo/product.jpg`. New and replacement uploads are still written to the configured disk and directory. Local paths are existence-checked, traversal is rejected, and external preview URLs must pass the package HTTPS and extension rules.

## Roles, users, and permissions

- `admin`: unrestricted;
- `manager`: initially unrestricted, permissions editable;
- `user`: no panel access by default;
- one role per user.

Add application permissions without editing the package:

```php
PageBlocks::permissions('Authors', [
    'authors.viewAny' => 'View authors',
    'authors.create' => 'Create authors',
    'authors.update' => 'Update authors',
    'authors.delete' => 'Delete authors',
]);
```

Permission files generated by `make:admin-model` are discovered from `app/Filament/Permissions`.

Publish the Users Resource for application-level edits:

```bash
php artisan page-blocks:publish-users
```

## Export administration state

```bash
php artisan page-blocks:export-admin-state
```

Creates `database/seeders/AdminStateSeeder.php` containing:

- roles and permissions;
- user name/email/system flag/role, without passwords;
- menus and items;
- GeneralInfo;
- system Pages and blocks.

Include manual Pages:

```bash
php artisan page-blocks:export-admin-state --with-custom-pages
```

Restore:

```bash
php artisan db:seed --class=Database\\Seeders\\AdminStateSeeder
```

Media paths are exported; media files are not. New restored users receive `PAGE_BLOCKS_STATE_USER_PASSWORD` or an unknown random password. Existing password hashes are unchanged.

## Cache

```php
'cache' => [
    'enabled' => true,
    'store' => null,
    'ttl' => 3600,
    'prefix' => 'filament-page-blocks',
],
```

Page, PageBlock, and GeneralInfo observers invalidate affected cache versions automatically.

## Main configuration groups

| Key | Purpose |
| --- | --- |
| `models`, `tables` | Replace package models and table names before migration |
| `blocks` | Built-in and application block classes loaded from config |
| `templates`, `default_template` | Allowed blocks and page layouts |
| `filament` | Pages Resource, navigation, width, filters, form columns |
| `routes` | Frontend route toggle, prefix, middleware, reserved slugs |
| `frontend` | layouts, assets, shared site values, navigation |
| `menus` | frontend handles and admin navigation synchronization |
| `authorization` | resources, user model, permissions and discovery paths |
| `media` | disks, directories, collections, validation |
| `fields` | shared Rich Text behavior |
| `generator` | block and application Resource output paths |
| `seeders` | GeneralInfo, menus, and demo users |
| `admin_state` | password for newly restored users |
| `cache` | rendered page cache |

## Commands

| Command | Purpose |
| --- | --- |
| `page-blocks:install` | Full package, Filament, Panel, migration, and seeder setup |
| `make:page-block {name}` | Generate and register a block; use `--view` or `--all` |
| `make:admin-model {name}` | Generate compact CRUD for an existing migrated model |
| `page-blocks:publish-users` | Publish editable Users Resource |
| `page-blocks:export-admin-state` | Generate a portable administration-state seeder |
| `page-blocks:import-legacy` | Import explicitly mapped page/block data; start with `--dry-run` |

Detailed references remain available in [`docs/`](docs/): configuration, block creation, Filament integration, frontend rendering, and customization.

## Deployment checklist

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan filament:upgrade
php artisan optimize
```

Before deployment:

- disable or replace fixed demo credentials;
- set `APP_URL` and storage disk correctly;
- deploy referenced media files;
- review generated admin-state seeders before committing;
- verify policies and manager permissions;
- keep application block classes and views in source control.
