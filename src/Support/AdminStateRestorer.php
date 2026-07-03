<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use DannAPI\FilamentPageBlocks\Models\Menu;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Models\PageBlock;
use DannAPI\FilamentPageBlocks\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class AdminStateRestorer
{
    /** @param array<string, mixed> $state */
    public function restore(array $state): void
    {
        if (($state['version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported Filament Page Blocks admin-state version.');
        }

        DB::transaction(function () use ($state): void {
            $this->restoreRoles((array) ($state['roles'] ?? []));
            $this->restoreUsers((array) ($state['users'] ?? []));
            $this->restoreGeneralInfo(is_array($state['general_info'] ?? null) ? $state['general_info'] : null);
            $this->restorePages((array) ($state['pages'] ?? []));
            $this->restoreMenus((array) ($state['menus'] ?? []));
        });
    }

    /** @param array<int, mixed> $roles */
    private function restoreRoles(array $roles): void
    {
        /** @var class-string<Role> $model */
        $model = config('filament-page-blocks.models.role', Role::class);
        foreach ($roles as $attributes) {
            if (! is_array($attributes) || ! is_string($attributes['slug'] ?? null)) {
                continue;
            }
            $model::query()->updateOrCreate(['slug' => $attributes['slug']], [
                'name' => (string) ($attributes['name'] ?? $attributes['slug']),
                'permissions' => array_values((array) ($attributes['permissions'] ?? [])),
                'is_system' => (bool) ($attributes['is_system'] ?? false),
            ]);
        }
    }

    /** @param array<int, mixed> $users */
    private function restoreUsers(array $users): void
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');
        /** @var class-string<Role> $roleModel */
        $roleModel = config('filament-page-blocks.models.role', Role::class);
        foreach ($users as $attributes) {
            if (! is_array($attributes) || ! is_string($attributes['email'] ?? null) || $attributes['email'] === '') {
                continue;
            }
            /** @var Model $user */
            $user = $userModel::query()->firstOrNew(['email' => $attributes['email']]);
            $user->forceFill([
                'name' => (string) ($attributes['name'] ?? $attributes['email']),
                'is_system' => (bool) ($attributes['is_system'] ?? false),
            ]);
            if (! $user->exists) {
                $password = config('filament-page-blocks.admin_state.user_password');
                $user->setAttribute('password', Hash::make(is_string($password) && $password !== '' ? $password : Str::password(48)));
            }
            $user->save();

            $roleSlug = $attributes['role'] ?? null;
            $roleId = is_string($roleSlug) ? $roleModel::query()->where('slug', $roleSlug)->value('id') : null;
            if (method_exists($user, 'roles')) {
                $user->roles()->sync($roleId === null ? [] : [$roleId]);
            }
        }
    }

    /** @param array<string, mixed>|null $attributes */
    private function restoreGeneralInfo(?array $attributes): void
    {
        if ($attributes === null) {
            return;
        }
        /** @var class-string<GeneralInfo> $model */
        $model = config('filament-page-blocks.models.general_info', GeneralInfo::class);
        $record = $model::singletonOrCreate();
        $record->forceFill([
            'data' => (array) ($attributes['data'] ?? []),
            'images' => (array) ($attributes['images'] ?? []),
            'rich_text' => (array) ($attributes['rich_text'] ?? []),
        ])->save();
    }

    /** @param array<int, mixed> $pages */
    private function restorePages(array $pages): void
    {
        /** @var class-string<Page> $pageModel */
        $pageModel = config('filament-page-blocks.models.page', Page::class);
        /** @var class-string<PageBlock> $blockModel */
        $blockModel = config('filament-page-blocks.models.page_block', PageBlock::class);
        foreach ($pages as $attributes) {
            if (! is_array($attributes) || ! is_string($attributes['slug'] ?? null)) {
                continue;
            }
            /** @var Page $page */
            $page = $pageModel::query()->withTrashed()->firstOrNew(['slug' => $attributes['slug']]);
            $page->forceFill([
                'title' => (string) ($attributes['title'] ?? $attributes['slug']),
                'status' => (string) ($attributes['status'] ?? 'draft'),
                'template' => (string) ($attributes['template'] ?? 'default'),
                'is_homepage' => (bool) ($attributes['is_homepage'] ?? false),
                'published_at' => $attributes['published_at'] ?? null,
                'seo_title' => $attributes['seo_title'] ?? null,
                'seo_description' => $attributes['seo_description'] ?? null,
                'sort' => (int) ($attributes['sort'] ?? 0),
                'is_system' => (bool) ($attributes['is_system'] ?? true),
            ]);
            $page->save();
            if ($page->trashed()) {
                $page->restore();
            }

            $keys = [];
            foreach ((array) ($attributes['blocks'] ?? []) as $blockAttributes) {
                if (! is_array($blockAttributes) || ! is_string($blockAttributes['key'] ?? null)) {
                    continue;
                }
                $keys[] = $blockAttributes['key'];
                $blockModel::query()->updateOrCreate(
                    ['page_id' => $page->getKey(), 'key' => $blockAttributes['key']],
                    [
                        'type' => (string) ($blockAttributes['type'] ?? ''),
                        'data' => (array) ($blockAttributes['data'] ?? []),
                        'sort' => (int) ($blockAttributes['sort'] ?? 0),
                        'is_visible' => (bool) ($blockAttributes['is_visible'] ?? true),
                        'is_system' => (bool) ($blockAttributes['is_system'] ?? $page->is_system),
                    ],
                );
            }
            $page->blocks()->whereNotIn('key', $keys)->get()->each(static function (PageBlock $block): void {
                $block->is_system ? $block->deleteFromSystemSynchronizer() : $block->delete();
            });
        }
    }

    /** @param array<int, mixed> $menus */
    private function restoreMenus(array $menus): void
    {
        /** @var class-string<Menu> $menuModel */
        $menuModel = config('filament-page-blocks.models.menu', Menu::class);
        /** @var class-string<Page> $pageModel */
        $pageModel = config('filament-page-blocks.models.page', Page::class);
        foreach ($menus as $attributes) {
            if (! is_array($attributes) || ! is_string($attributes['handle'] ?? null)) {
                continue;
            }
            $menu = $menuModel::query()->updateOrCreate(['handle' => $attributes['handle']], [
                'name' => (string) ($attributes['name'] ?? $attributes['handle']),
            ]);
            $menu->allItems()->delete();
            $references = [];
            $items = (array) ($attributes['items'] ?? []);
            foreach ([null, 'children'] as $pass) {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $parentReference = $item['parent_reference'] ?? null;
                    if (($pass === null) !== ($parentReference === null)) {
                        continue;
                    }
                    $parentId = is_string($parentReference) ? ($references[$parentReference] ?? null) : null;
                    if (is_string($parentReference) && $parentId === null) {
                        continue;
                    }
                    $pageId = is_string($item['page_slug'] ?? null)
                        ? $pageModel::query()->where('slug', $item['page_slug'])->value('id')
                        : null;
                    $created = $menu->allItems()->create([
                        'parent_id' => $parentId,
                        'page_id' => $pageId,
                        'label' => (string) ($item['label'] ?? ''),
                        'link_type' => (string) ($item['link_type'] ?? 'custom'),
                        'url' => $item['url'] ?? null,
                        'icon' => $item['icon'] ?? null,
                        'target' => (string) ($item['target'] ?? '_self'),
                        'sort' => (int) ($item['sort'] ?? 0),
                        'is_visible' => (bool) ($item['is_visible'] ?? true),
                    ]);
                    if (is_string($item['reference'] ?? null)) {
                        $references[$item['reference']] = $created->getKey();
                    }
                }
            }
        }
    }
}
