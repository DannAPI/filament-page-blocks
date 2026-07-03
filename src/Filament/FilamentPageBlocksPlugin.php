<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament;

use DannAPI\FilamentPageBlocks\Contracts\BlockContract;
use DannAPI\FilamentPageBlocks\Data\PageTemplate;
use DannAPI\FilamentPageBlocks\Filament\Resources\PageResource\Pages\ListPages;
use DannAPI\FilamentPageBlocks\Registry\BlockRegistry;
use DannAPI\FilamentPageBlocks\Registry\PageTemplateRegistry;
use DannAPI\FilamentPageBlocks\Support\AdminNavigationManager;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

final class FilamentPageBlocksPlugin implements Plugin
{
    /** @var array<class-string<BlockContract>> */
    private array $blocks = [];

    /** @var array<PageTemplate> */
    private array $templates = [];

    private bool $resourceEnabled = true;

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'filament-page-blocks';
    }

    /** @param array<class-string<BlockContract>> $blocks */
    public function blocks(array $blocks): self
    {
        $this->blocks = $blocks;

        return $this;
    }

    /** @param array<PageTemplate> $templates */
    public function templates(array $templates): self
    {
        $this->templates = $templates;

        return $this;
    }

    public function resource(bool $enabled = true): self
    {
        $this->resourceEnabled = $enabled;

        return $this;
    }

    public function register(Panel $panel): void
    {
        app(BlockRegistry::class)->register($this->blocks);
        app(PageTemplateRegistry::class)->register($this->templates);

        $maxContentWidth = config('filament-page-blocks.filament.max_content_width', 'full');
        if (is_string($maxContentWidth) && $maxContentWidth !== '') {
            $panel->maxContentWidth($maxContentWidth);
        }

        if ($this->resourceEnabled && config('filament-page-blocks.filament.resource_enabled', true)) {
            $panel->resources([(string) config('filament-page-blocks.filament.resource', Resources\PageResource::class)]);
        }
        if (config('filament-page-blocks.general_info.resource_enabled', true)) {
            $panel->resources([(string) config(
                'filament-page-blocks.general_info.resource',
                Resources\GeneralInfoResource::class,
            )]);
        }
        if (config('filament-page-blocks.menus.resource_enabled', true)) {
            $panel->resources([(string) config('filament-page-blocks.menus.resource', Resources\MenuResource::class)]);
        }
        if (config('filament-page-blocks.authorization.roles_resource_enabled', true)) {
            $panel->resources([(string) config('filament-page-blocks.authorization.roles_resource', Resources\RoleResource::class)]);
        }
        if (config('filament-page-blocks.authorization.users_resource_enabled', true)) {
            $panel->resources([(string) config('filament-page-blocks.authorization.users_resource', Resources\UserResource::class)]);
        }
        if (config('filament-page-blocks.media.library.enabled', true)) {
            $panel->pages([(string) config(
                'filament-page-blocks.media.library.page',
                Pages\MediaLibraryPage::class,
            )]);
        }

        if (config('filament-page-blocks.menus.admin.enabled', true)) {
            $panel->navigation(
                static fn (NavigationBuilder $builder): NavigationBuilder => app(AdminNavigationManager::class)
                    ->build($builder, $panel),
            );
        }

        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            static fn () => view('filament-page-blocks::filament.page-order-lock'),
            scopes: [ListPages::class],
        );
        $panel->renderHook(
            PanelsRenderHook::STYLES_AFTER,
            static fn () => view('filament-page-blocks::filament.admin-rich-editor'),
        );
        $panel->renderHook(
            PanelsRenderHook::STYLES_AFTER,
            static fn () => view('filament-page-blocks::filament.navigation-dropdown'),
        );
        $panel->renderHook(
            PanelsRenderHook::STYLES_AFTER,
            static fn () => view('filament-page-blocks::filament.page-form'),
        );
        $panel->renderHook(
            PanelsRenderHook::STYLES_AFTER,
            static fn () => view('filament-page-blocks::filament.icon-picker'),
        );
    }

    public function boot(Panel $panel): void {}
}
