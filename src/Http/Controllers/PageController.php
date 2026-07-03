<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Http\Controllers;

use DannAPI\FilamentPageBlocks\Contracts\PageBlocksRenderer;
use DannAPI\FilamentPageBlocks\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

final readonly class PageController
{
    public function __invoke(PageBlocksRenderer $renderer, string $slug = '/'): View|Response
    {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);
        $page = $model::query()->published()->where('slug', $slug)->first();

        if ($page === null) {
            return response()->view('filament-page-blocks::errors.404', [
                'slug' => $slug,
            ], 404);
        }

        return $renderer->render($page);
    }
}
