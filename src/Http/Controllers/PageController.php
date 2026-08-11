<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Http\Controllers;

use DannAPI\FilamentPageBlocks\Contracts\FrontendDataProvider;
use DannAPI\FilamentPageBlocks\Contracts\PageBlocksRenderer;
use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Registry\PageTemplateRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PageController
{
    public function __invoke(
        PageBlocksRenderer $renderer,
        FrontendDataProvider $frontendData,
        PageTemplateRegistry $templates,
        string $slug = '/',
    ): View|Response {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);
        $page = $model::query()->published()->where('slug', $slug)->first();

        if ($page === null) {
            $templateIdentifier = (string) config('filament-page-blocks.default_template', 'default');
            $notFoundPage = new $model;
            $notFoundPage->forceFill([
                'title' => 'Page not found',
                'slug' => $slug,
                'status' => PageStatus::Draft,
                'template' => $templateIdentifier,
                'is_homepage' => false,
                'seo_title' => 'Page not found',
                'seo_description' => null,
            ]);

            $view = view()->exists('errors.404')
                ? 'errors.404'
                : 'filament-page-blocks::errors.404';

            return response()->view($view, [
                ...$frontendData->data($notFoundPage),
                'page' => $notFoundPage,
                'content' => '',
                'template' => $templates->get($templateIdentifier),
                'slug' => $slug,
                'exception' => new NotFoundHttpException,
            ], 404);
        }

        return $renderer->render($page);
    }
}
