<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Models\Page;
use Illuminate\Support\Facades\Route;

final class PageUrlGenerator
{
    public function to(Page $page): string
    {
        return $this->toSlug($page->slug);
    }

    public function toSlug(string $slug = '/'): string
    {
        if (preg_match('~^(?:https?:)?//|^(?:#|mailto:|tel:)~', $slug) === 1) {
            return $slug;
        }

        $slug = trim($slug);
        $slug = preg_replace('~^(?:\./)+~', '', $slug) ?? $slug;
        $slug = preg_replace('~\.php$~i', '', $slug) ?? $slug;
        if ($slug === 'index') {
            $slug = '/';
        }
        $slug = $slug === '' || $slug === '/' ? '/' : trim($slug, '/');
        $routeName = (string) config('filament-page-blocks.routes.name', 'filament-page-blocks.show');

        if ($slug === '/' && Route::has($routeName.'.home')) {
            return route($routeName.'.home');
        }

        if ($slug !== '/' && Route::has($routeName)) {
            return route($routeName, ['slug' => $slug]);
        }

        return url($slug === '/' ? '/' : '/'.$slug);
    }
}
