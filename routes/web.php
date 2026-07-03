<?php

declare(strict_types=1);

use DannAPI\FilamentPageBlocks\Http\Controllers\PageController;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

$routePrefix = trim((string) config('filament-page-blocks.routes.prefix', ''), '/');
$reservedSlugs = (array) config('filament-page-blocks.routes.reserved_slugs', ['admin', 'api', 'livewire']);

if ($routePrefix === '') {
    $reservedSlugs = [
        ...$reservedSlugs,
        ...array_map(
            static fn (Panel $panel): string => explode('/', trim($panel->getPath(), '/'))[0] ?? '',
            Filament::getPanels(),
        ),
    ];
}

$reservedSlugs = array_values(array_unique(array_filter(
    $reservedSlugs,
    static fn (mixed $slug): bool => is_string($slug) && preg_match('/^[A-Za-z0-9_-]+$/', $slug) === 1,
)));
$slugPattern = '[A-Za-z0-9_-]+';

if ($reservedSlugs !== []) {
    $escapedSlugs = array_map(
        static fn (string $slug): string => preg_quote($slug, '/'),
        $reservedSlugs,
    );
    $slugPattern = '(?!(?:'.implode('|', $escapedSlugs).')$)'.$slugPattern;
}

Route::middleware((array) config('filament-page-blocks.routes.middleware', ['web']))
    ->prefix($routePrefix)
    ->group(function () use ($slugPattern): void {
        $name = (string) config('filament-page-blocks.routes.name', 'filament-page-blocks.show');

        Route::get('/', PageController::class)
            ->defaults('slug', '/')
            ->name($name.'.home');

        Route::get('/{slug}', PageController::class)
            ->where('slug', $slugPattern)
            ->name($name);
    });
