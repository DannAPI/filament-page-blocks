<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use BackedEnum;
use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use DannAPI\FilamentPageBlocks\Models\Menu;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Models\Role;
use Illuminate\Database\Eloquent\Model;

final class AdminStateExporter
{
    /** @return array<string, mixed> */
    public function export(bool $withCustomPages = false): array
    {
        return [
            'version' => 1,
            'roles' => $this->roles(),
            'users' => $this->users(),
            'general_info' => $this->generalInfo(),
            'pages' => $this->pages($withCustomPages),
            'menus' => $this->menus(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function roles(): array
    {
        /** @var class-string<Role> $model */
        $model = config('filament-page-blocks.models.role', Role::class);

        return $model::query()->orderBy('id')->get()->map(static fn (Role $role): array => [
            'name' => (string) $role->name,
            'slug' => (string) $role->slug,
            'permissions' => array_values((array) $role->permissions),
            'is_system' => (bool) $role->is_system,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function users(): array
    {
        /** @var class-string<Model> $model */
        $model = config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');

        return $model::query()->with('roles')->orderBy('id')->get()->map(static fn (Model $user): array => [
            'name' => (string) $user->getAttribute('name'),
            'email' => (string) $user->getAttribute('email'),
            'is_system' => (bool) $user->getAttribute('is_system'),
            'role' => $user->getRelation('roles')->first()?->getAttribute('slug'),
        ])->all();
    }

    /** @return array<string, mixed>|null */
    private function generalInfo(): ?array
    {
        /** @var class-string<GeneralInfo> $model */
        $model = config('filament-page-blocks.models.general_info', GeneralInfo::class);
        $record = $model::query()->first();

        return $record === null ? null : [
            'data' => (array) $record->data,
            'images' => (array) $record->images,
            'rich_text' => (array) $record->rich_text,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function pages(bool $withCustomPages): array
    {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);
        $query = $model::query()->with('blocks')->orderBy('sort')->orderBy('id');
        if (! $withCustomPages) {
            $query->where('is_system', true);
        }

        return $query->get()->map(function (Page $page): array {
            $status = $page->status;

            return [
                'title' => (string) $page->title,
                'slug' => (string) $page->slug,
                'status' => $status instanceof BackedEnum ? $status->value : (string) $status,
                'template' => (string) $page->template,
                'is_homepage' => (bool) $page->is_homepage,
                'published_at' => $page->published_at?->toISOString(),
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'sort' => (int) $page->sort,
                'is_system' => (bool) $page->is_system,
                'blocks' => $page->blocks->map(static fn ($block): array => [
                    'key' => (string) $block->key,
                    'type' => (string) $block->type,
                    'data' => (array) $block->data,
                    'sort' => (int) $block->sort,
                    'is_visible' => (bool) $block->is_visible,
                    'is_system' => (bool) $block->is_system,
                ])->all(),
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function menus(): array
    {
        /** @var class-string<Menu> $model */
        $model = config('filament-page-blocks.models.menu', Menu::class);

        return $model::query()->with(['allItems.page'])->orderBy('id')->get()->map(static function (Menu $menu): array {
            return [
                'name' => (string) $menu->name,
                'handle' => (string) $menu->handle,
                'items' => $menu->allItems->map(static fn ($item): array => [
                    'reference' => 'item-'.$item->getKey(),
                    'parent_reference' => $item->parent_id === null ? null : 'item-'.$item->parent_id,
                    'page_slug' => $item->page?->slug,
                    'label' => (string) $item->label,
                    'link_type' => $item->link_type instanceof BackedEnum ? $item->link_type->value : (string) $item->link_type,
                    'url' => $item->url,
                    'icon' => $item->icon,
                    'target' => (string) $item->target,
                    'sort' => (int) $item->sort,
                    'is_visible' => (bool) $item->is_visible,
                ])->all(),
            ];
        })->all();
    }
}
