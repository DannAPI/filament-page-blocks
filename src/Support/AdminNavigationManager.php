<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Enums\MenuLinkType;
use DannAPI\FilamentPageBlocks\Models\Menu;
use DannAPI\FilamentPageBlocks\Models\MenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page as FilamentPage;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use UnitEnum;

final class AdminNavigationManager
{
    private const string NAVIGATION_ITEM_PREFIX = '@navigation:';

    /** @return array<string, string> */
    public function options(?Panel $panel = null): array
    {
        $panel ??= filament()->getCurrentOrDefaultPanel();
        $options = [];

        foreach ($this->targets($panel) as $target) {
            if (! $this->isAvailable($target, $panel)) {
                continue;
            }

            $options[$target] = $this->targetLabel($target, $panel);
        }

        asort($options);

        return $options;
    }

    public function label(string $target, ?Panel $panel = null): ?string
    {
        return $this->options($panel)[$target] ?? null;
    }

    public function build(NavigationBuilder $builder, Panel $panel): NavigationBuilder
    {
        $menu = config('filament-page-blocks.menus.admin.auto_sync', true)
            ? $this->sync($panel)
            : $this->adminMenu();

        if ($menu === null || ! $menu->allItems()->exists()) {
            return $this->buildDefault($builder, $panel);
        }

        $items = $menu->items()
            ->with(['children' => static fn ($query) => $query->where('is_visible', true)])
            ->where('is_visible', true)
            ->get()
            ->flatMap(fn (MenuItem $item): array => $this->navigationItems($item, $panel))
            ->values()
            ->all();

        return $builder->items($items);
    }

    public function sync(Panel $panel): ?Menu
    {
        $menu = $this->adminMenu(create: true);
        if ($menu === null) {
            return null;
        }

        try {
            $existing = $menu->allItems()
                ->where('link_type', MenuLinkType::Admin->value)
                ->whereNotNull('url')
                ->pluck('url')
                ->all();
            $missing = array_values(array_diff($this->orderedTargets($panel), $existing));
            $iconsSupported = $this->menuIconsSupported($menu);

            $menu->getConnection()->transaction(function () use ($menu, $missing, $panel, $iconsSupported): void {
                $sort = ((int) $menu->allItems()->max('sort')) + 1;

                foreach ($missing as $target) {
                    $attributes = [
                        'label' => $this->targetLabel($target, $panel),
                        'link_type' => MenuLinkType::Admin,
                        'url' => $target,
                        'target' => '_self',
                        'sort' => $sort++,
                        'is_visible' => true,
                    ];
                    if ($iconsSupported) {
                        $attributes['icon'] = $this->defaultIcon($target, $panel);
                    }

                    $menu->allItems()->create($attributes);
                }

                if (! $iconsSupported) {
                    return;
                }

                $menu->allItems()
                    ->where('link_type', MenuLinkType::Admin->value)
                    ->whereNotNull('url')
                    ->get()
                    ->each(function (MenuItem $item) use ($panel): void {
                        if (app(HeroiconOptions::class)->contains($item->icon)) {
                            return;
                        }

                        $icon = $this->defaultIcon((string) $item->url, $panel);
                        if ($icon === null) {
                            return;
                        }

                        $item->icon = $icon;
                        $item->saveQuietly();
                    });
            });

            return $menu;
        } catch (QueryException) {
            return null;
        }
    }

    public function url(string $target, ?Panel $panel = null): ?string
    {
        $panel ??= filament()->getCurrentOrDefaultPanel();

        $item = $this->targetItems($target, $panel)[0] ?? null;

        return $item?->getUrl();
    }

    private function adminMenu(bool $create = false): ?Menu
    {
        /** @var class-string<Menu> $model */
        $model = config('filament-page-blocks.models.menu', Menu::class);
        $handle = config('filament-page-blocks.menus.admin.handle', 'admin');

        if (! is_string($handle) || $handle === '') {
            return null;
        }

        try {
            if ($create) {
                return $model::query()->firstOrCreate(
                    ['handle' => $handle],
                    ['name' => 'Admin'],
                );
            }

            return $model::query()->where('handle', $handle)->first();
        } catch (QueryException) {
            return null;
        }
    }

    /** @return array<NavigationItem> */
    private function defaultItems(Panel $panel): array
    {
        return collect($this->targets($panel))
            ->flatMap(fn (string $target): array => $this->targetItems($target, $panel))
            ->sortBy(fn (NavigationItem $item): int => $item->getSort())
            ->values()
            ->all();
    }

    private function buildDefault(NavigationBuilder $builder, Panel $panel): NavigationBuilder
    {
        $items = collect($this->defaultItems($panel));
        $builder->items($items->filter(fn (NavigationItem $item): bool => $item->getGroup() === null)->values()->all());

        $items
            ->filter(fn (NavigationItem $item): bool => $item->getGroup() !== null)
            ->groupBy(static function (NavigationItem $item): string {
                $group = $item->getGroup();

                return $group instanceof UnitEnum ? $group::class.'::'.$group->name : (string) $group;
            })
            ->each(static function (Collection $groupItems) use ($builder): void {
                /** @var NavigationItem $first */
                $first = $groupItems->first();
                $group = $first->getGroup();
                $label = $group instanceof UnitEnum ? $group->name : (string) $group;

                $builder->group(NavigationGroup::make($label)->items($groupItems->values()->all()));
            });

        return $builder;
    }

    /** @return array<NavigationItem> */
    private function navigationItems(MenuItem $menuItem, Panel $panel): array
    {
        if ($menuItem->link_type === MenuLinkType::Custom) {
            $url = app(MenuUrlResolver::class)->resolve($menuItem);
            $children = $this->childNavigationItems($menuItem, $panel);
            $isDropdown = str_starts_with(trim((string) $menuItem->url), '#') && $children !== [];
            $icon = app(HeroiconOptions::class)->contains($menuItem->icon)
                ? (string) $menuItem->icon
                : 'heroicon-o-link';
            $item = NavigationItem::make($menuItem->label)
                ->icon($icon)
                ->sort($menuItem->sort)
                ->url($isDropdown ? null : $url, ! $isDropdown && $menuItem->target === '_blank')
                ->isActiveWhen(static fn (): bool => rtrim(url()->current(), '/') === rtrim($url, '/'));
            $this->showSelectedChildIcon($item, $menuItem);

            if ($children !== []) {
                $item->childItems($children);
            }

            if ($isDropdown) {
                $isChildItemActive = $item->isChildItemsActive();

                $item->extraAttributes([
                    'class' => 'fi-fpb-navigation-dropdown',
                    'x-data' => '{ fpbDropdownOpen: '.($isChildItemActive ? 'true' : 'false').' }',
                    'x-bind:class' => "{ 'fi-fpb-navigation-dropdown-open': fpbDropdownOpen }",
                    'x-on:livewire:navigated.window' => "if (\$el.classList.contains('fi-sidebar-item-has-active-child-items')) { fpbDropdownOpen = true }",
                    'x-on:click.capture' => "if (\$event.target.closest('.fi-sidebar-item-btn') === \$event.currentTarget.querySelector(':scope > .fi-sidebar-item-btn')) { \$event.preventDefault(); \$event.stopPropagation(); fpbDropdownOpen = ! fpbDropdownOpen }",
                ]);
            }

            return [$item];
        }

        if ($menuItem->link_type !== MenuLinkType::Admin || ! is_string($menuItem->url)) {
            return [];
        }

        $items = $this->targetItems($menuItem->url, $panel);
        foreach ($items as $item) {
            $item->label($menuItem->label)->sort($menuItem->sort);

            if (app(HeroiconOptions::class)->contains($menuItem->icon)) {
                $item->icon((string) $menuItem->icon);
            }
            $this->showSelectedChildIcon($item, $menuItem);

            $children = $this->childNavigationItems($menuItem, $panel);

            if ($children !== []) {
                $item->childItems($children);
            }
        }

        return $items;
    }

    private function showSelectedChildIcon(NavigationItem $item, MenuItem $menuItem): void
    {
        if ($menuItem->parent_id === null || ! app(HeroiconOptions::class)->contains($menuItem->icon)) {
            return;
        }

        $mask = app(HeroiconOptions::class)->cssMask($menuItem->icon);
        if ($mask === null) {
            return;
        }

        $item->extraAttributes([
            'class' => 'fi-fpb-navigation-child-icon',
            'style' => "--fi-fpb-child-icon: {$mask};",
        ], merge: true);
    }

    private function menuIconsSupported(Menu $menu): bool
    {
        return $menu->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($menu->allItems()->getRelated()->getTable(), 'icon');
    }

    private function defaultIcon(string $target, Panel $panel): ?string
    {
        $icon = ($this->targetItems($target, $panel)[0] ?? null)?->getIcon();

        if ($icon instanceof Heroicon) {
            $baseName = preg_replace('/^(?:o|m|c)-/', '', $icon->value);
            $outlined = is_string($baseName) ? 'heroicon-o-'.$baseName : null;

            return app(HeroiconOptions::class)->contains($outlined) ? $outlined : null;
        }

        return app(HeroiconOptions::class)->contains($icon) ? $icon : null;
    }

    /** @return array<NavigationItem> */
    private function childNavigationItems(MenuItem $menuItem, Panel $panel): array
    {
        if ($menuItem->link_type === MenuLinkType::Admin) {
            return [];
        }

        return $menuItem->children
            ->flatMap(fn (MenuItem $child): array => $this->navigationItems($child, $panel))
            ->values()
            ->all();
    }

    /** @return array<NavigationItem> */
    private function targetItems(string $target, Panel $panel): array
    {
        if (str_starts_with($target, self::NAVIGATION_ITEM_PREFIX)) {
            $item = $this->panelNavigationItems($panel)[$target] ?? null;

            return $item !== null && $item->isVisible() ? [clone $item] : [];
        }

        if (! $this->isAvailable($target, $panel) || ! $target::canAccess()) {
            return [];
        }

        return array_values(array_filter(
            $target::getNavigationItems(),
            static fn (mixed $item): bool => $item instanceof NavigationItem,
        ));
    }

    /** @return array<string> */
    private function targets(Panel $panel): array
    {
        return array_values(array_unique([
            ...$panel->getPages(),
            ...$panel->getResources(),
            ...array_keys($this->panelNavigationItems($panel)),
        ]));
    }

    /** @return array<string> */
    private function orderedTargets(Panel $panel): array
    {
        return collect($this->targets($panel))
            ->filter(fn (string $target): bool => $this->isAvailable($target, $panel))
            ->sortBy(fn (string $target): int => $this->targetSort($target, $panel))
            ->values()
            ->all();
    }

    private function isAvailable(string $target, Panel $panel): bool
    {
        if (str_starts_with($target, self::NAVIGATION_ITEM_PREFIX)) {
            return isset($this->panelNavigationItems($panel)[$target]);
        }

        if (! is_subclass_of($target, Resource::class) && ! is_subclass_of($target, FilamentPage::class)) {
            return false;
        }

        return in_array($target, $this->targets($panel), true) && $target::shouldRegisterNavigation();
    }

    private function targetLabel(string $target, Panel $panel): string
    {
        if (str_starts_with($target, self::NAVIGATION_ITEM_PREFIX)) {
            return $this->panelNavigationItems($panel)[$target]->getLabel();
        }

        return $target::getNavigationLabel();
    }

    private function targetSort(string $target, Panel $panel): int
    {
        if (str_starts_with($target, self::NAVIGATION_ITEM_PREFIX)) {
            return $this->panelNavigationItems($panel)[$target]->getSort();
        }

        return $target::getNavigationSort() ?? -1;
    }

    /** @return array<string, NavigationItem> */
    private function panelNavigationItems(Panel $panel): array
    {
        $items = [];

        foreach ($panel->getNavigationItems() as $item) {
            $identity = $item->getUrl() ?? $item->getLabel();
            $items[self::NAVIGATION_ITEM_PREFIX.sha1($identity)] = $item;
        }

        return $items;
    }
}
