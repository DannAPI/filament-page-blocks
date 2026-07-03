<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Rendering;

use DannAPI\FilamentPageBlocks\Contracts\FrontendDataProvider;
use DannAPI\FilamentPageBlocks\Data\FrontendAssets;
use DannAPI\FilamentPageBlocks\Data\FrontendContext;
use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Support\MenuRepository;
use DannAPI\FilamentPageBlocks\Support\PageUrlGenerator;

final readonly class DefaultFrontendDataProvider implements FrontendDataProvider
{
    public function __construct(private PageUrlGenerator $urls, private MenuRepository $menus) {}

    public function data(Page $page): array
    {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);
        $navigation = collect();

        if (config('filament-page-blocks.frontend.navigation.enabled', true)) {
            $navigation = $model::query()
                ->published()
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->map(fn (Page $item): array => [
                    'label' => $item->title,
                    'url' => $this->urls->to($item),
                    'active' => $item->is($page),
                    'page' => $item,
                ]);
        }

        $assets = FrontendAssets::fromConfig();
        $frontend = new FrontendContext(
            page: $page,
            assets: $assets,
            urls: $this->urls,
            pageKeys: array_filter(
                (array) config('filament-page-blocks.frontend.page_keys', []),
                static fn (mixed $key, mixed $slug): bool => is_string($slug) && is_string($key),
                ARRAY_FILTER_USE_BOTH,
            ),
        );

        /** @var class-string<GeneralInfo> $generalInfoModel */
        $generalInfoModel = config('filament-page-blocks.models.general_info', GeneralInfo::class);

        return [
            'assets' => $assets,
            'frontend' => $frontend,
            'pageName' => $frontend->key(),
            'isHomepage' => $frontend->isHome(),
            'homeUrl' => $frontend->url(),
            'headerMenu' => $this->menus->find(config('filament-page-blocks.menus.header')),
            'footerMenu' => $this->menus->find(config('filament-page-blocks.menus.footer')),
            'generalInfo' => $generalInfoModel::singleton(),
            'navigation' => $navigation,
            'site' => (array) config('filament-page-blocks.frontend.site', []),
        ];
    }
}
