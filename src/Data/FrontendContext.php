<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Data;

use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Support\PageUrlGenerator;

final readonly class FrontendContext
{
    /** @param array<string, string> $pageKeys */
    public function __construct(
        public Page $page,
        private FrontendAssets $assets,
        private PageUrlGenerator $urls,
        private array $pageKeys = [],
    ) {}

    public function key(): string
    {
        if (isset($this->pageKeys[$this->page->slug])) {
            return $this->pageKeys[$this->page->slug];
        }

        return $this->page->slug === '/' ? 'home' : trim($this->page->slug, '/');
    }

    public function is(string ...$keys): bool
    {
        return in_array($this->key(), $keys, true);
    }

    public function isHome(): bool
    {
        return $this->page->is_homepage || $this->page->slug === '/';
    }

    public function asset(string $path): string
    {
        return $this->assets->url($path);
    }

    public function url(string $slug = '/'): string
    {
        return $this->urls->toSlug($slug);
    }
}
