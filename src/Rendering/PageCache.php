<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Rendering;

use DannAPI\FilamentPageBlocks\Models\Page;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class PageCache
{
    private function store(): Repository
    {
        $store = config('filament-page-blocks.cache.store');

        return Cache::store(is_string($store) ? $store : null);
    }

    public function enabled(): bool
    {
        return (bool) config('filament-page-blocks.cache.enabled', false);
    }

    public function remember(Page $page, callable $callback): string
    {
        if (! $this->enabled()) {
            return (string) $callback();
        }
        $version = $this->store()->get($this->versionKey($page), 1);
        $globalVersion = $this->store()->get($this->globalVersionKey(), 1);
        $key = sprintf('%s:page:%d:v%s:g%s:%s', config('filament-page-blocks.cache.prefix', 'filament-page-blocks'), $page->getKey(), $version, $globalVersion, app()->getLocale());

        return (string) $this->store()->remember($key, (int) config('filament-page-blocks.cache.ttl', 3600), $callback);
    }

    public function forget(Page|int $page): void
    {
        $id = $page instanceof Page ? (int) $page->getKey() : $page;
        $key = sprintf('%s:page:%d:version', config('filament-page-blocks.cache.prefix', 'filament-page-blocks'), $id);
        $this->store()->forever($key, (int) $this->store()->get($key, 1) + 1);
    }

    public function forgetAll(): void
    {
        $key = $this->globalVersionKey();
        $this->store()->forever($key, (int) $this->store()->get($key, 1) + 1);
    }

    private function versionKey(Page $page): string
    {
        return sprintf('%s:page:%d:version', config('filament-page-blocks.cache.prefix', 'filament-page-blocks'), $page->getKey());
    }

    private function globalVersionKey(): string
    {
        return sprintf('%s:pages:version', config('filament-page-blocks.cache.prefix', 'filament-page-blocks'));
    }
}
