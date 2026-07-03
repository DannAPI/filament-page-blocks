<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks;

use DannAPI\FilamentPageBlocks\Commands\ExportAdminStateCommand;
use DannAPI\FilamentPageBlocks\Commands\ImportLegacyPageBlocksCommand;
use DannAPI\FilamentPageBlocks\Commands\InstallPageBlocksCommand;
use DannAPI\FilamentPageBlocks\Commands\MakeAdminModelCommand;
use DannAPI\FilamentPageBlocks\Commands\MakePageBlockCommand;
use DannAPI\FilamentPageBlocks\Commands\PublishUsersResourceCommand;
use DannAPI\FilamentPageBlocks\Contracts\FrontendDataProvider;
use DannAPI\FilamentPageBlocks\Contracts\PageBlocksRenderer;
use DannAPI\FilamentPageBlocks\Data\PageTemplate;
use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use DannAPI\FilamentPageBlocks\Models\Menu;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Models\PageBlock;
use DannAPI\FilamentPageBlocks\Models\Role;
use DannAPI\FilamentPageBlocks\Observers\GeneralInfoObserver;
use DannAPI\FilamentPageBlocks\Observers\PageBlockObserver;
use DannAPI\FilamentPageBlocks\Observers\PageObserver;
use DannAPI\FilamentPageBlocks\Policies\GeneralInfoPolicy;
use DannAPI\FilamentPageBlocks\Policies\MenuPolicy;
use DannAPI\FilamentPageBlocks\Policies\PagePolicy;
use DannAPI\FilamentPageBlocks\Policies\RolePolicy;
use DannAPI\FilamentPageBlocks\Policies\UserPolicy;
use DannAPI\FilamentPageBlocks\Registry\BlockRegistry;
use DannAPI\FilamentPageBlocks\Registry\PageTemplateRegistry;
use DannAPI\FilamentPageBlocks\Registry\PermissionRegistry;
use DannAPI\FilamentPageBlocks\Rendering\DefaultFrontendDataProvider;
use DannAPI\FilamentPageBlocks\Support\AdminNavigationManager;
use DannAPI\FilamentPageBlocks\Support\HeroiconOptions;
use DannAPI\FilamentPageBlocks\Support\MediaLibrary;
use DannAPI\FilamentPageBlocks\Support\MenuRepository;
use DannAPI\FilamentPageBlocks\Support\PermissionDiscovery;
use DannAPI\FilamentPageBlocks\Support\RichTextExcerpt;
use DannAPI\FilamentPageBlocks\Support\RoleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class PageBlocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-page-blocks.php', 'filament-page-blocks');
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(PageTemplateRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(RoleAccess::class);
        $this->app->singleton(PageBlocksManager::class);
        $this->app->singleton(MenuRepository::class);
        $this->app->singleton(MediaLibrary::class);
        $this->app->singleton(AdminNavigationManager::class);
        $this->app->singleton(HeroiconOptions::class);
        $this->app->singleton(PermissionDiscovery::class);
        $this->app->singleton(RichTextExcerpt::class);
        $this->app->singleton(PageBlocksRenderer::class, fn ($app) => $app->make((string) config('filament-page-blocks.rendering.renderer')));
        $this->app->singleton(FrontendDataProvider::class, fn ($app) => $app->make(
            (string) config('filament-page-blocks.frontend.data_provider', DefaultFrontendDataProvider::class),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-page-blocks');
        $this->publishes([__DIR__.'/../config/filament-page-blocks.php' => config_path('filament-page-blocks.php')], 'filament-page-blocks-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'filament-page-blocks-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/filament-page-blocks')], 'filament-page-blocks-views');

        app(BlockRegistry::class)->register((array) config('filament-page-blocks.blocks', []));
        foreach ((array) config('filament-page-blocks.authorization.permissions', []) as $group => $permissions) {
            if (is_array($permissions)) {
                app(PermissionRegistry::class)->register((string) $group, $permissions);
            }
        }
        foreach (app(PermissionDiscovery::class)->discover(
            (array) config('filament-page-blocks.authorization.permission_paths', []),
        ) as $group => $permissions) {
            app(PermissionRegistry::class)->register($group, $permissions);
        }
        foreach (app(PermissionRegistry::class)->keys() as $permission) {
            if (! Gate::has($permission)) {
                Gate::define($permission, fn ($user): bool => app(RoleAccess::class)->allows($user, $permission));
            }
        }
        $templates = [];
        foreach ((array) config('filament-page-blocks.templates', []) as $identifier => $definition) {
            if ($definition instanceof PageTemplate) {
                $templates[] = $definition;

                continue;
            }
            if (! is_array($definition)) {
                continue;
            }
            $templates[] = PageTemplate::from(
                (string) $identifier,
                (string) ($definition['label'] ?? $identifier),
                is_array($definition['blocks'] ?? null) ? $definition['blocks'] : '*',
                (string) ($definition['layout'] ?? 'filament-page-blocks::pages.default'),
            );
        }
        app(PageTemplateRegistry::class)->register($templates);

        /** @var class-string<Page> $pageModel */
        $pageModel = config('filament-page-blocks.models.page', Page::class);
        /** @var class-string<PageBlock> $blockModel */
        $blockModel = config('filament-page-blocks.models.page_block', PageBlock::class);
        $pageModel::observe(PageObserver::class);
        $blockModel::observe(PageBlockObserver::class);
        /** @var class-string<GeneralInfo> $generalInfoModel */
        $generalInfoModel = config('filament-page-blocks.models.general_info', GeneralInfo::class);
        $generalInfoModel::observe(GeneralInfoObserver::class);
        Gate::policy($generalInfoModel, GeneralInfoPolicy::class);
        Gate::policy($pageModel, PagePolicy::class);
        /** @var class-string<Menu> $menuModel */
        $menuModel = config('filament-page-blocks.models.menu', Menu::class);
        Gate::policy($menuModel, MenuPolicy::class);
        /** @var class-string<Role> $roleModel */
        $roleModel = config('filament-page-blocks.models.role', Role::class);
        Gate::policy($roleModel, RolePolicy::class);
        /** @var class-string<Model> $userModel */
        $userModel = config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');
        Gate::policy($userModel, UserPolicy::class);

        if (config('filament-page-blocks.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakePageBlockCommand::class,
                InstallPageBlocksCommand::class,
                ExportAdminStateCommand::class,
                MakeAdminModelCommand::class,
                PublishUsersResourceCommand::class,
                ImportLegacyPageBlocksCommand::class,
            ]);
        }
    }
}
