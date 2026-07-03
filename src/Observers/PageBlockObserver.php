<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Observers;

use DannAPI\FilamentPageBlocks\Models\PageBlock;
use DannAPI\FilamentPageBlocks\Rendering\PageCache;

final readonly class PageBlockObserver
{
    public function __construct(private PageCache $cache) {}

    public function saved(PageBlock $block): void
    {
        $this->cache->forget((int) $block->page_id);
    }

    public function deleted(PageBlock $block): void
    {
        $this->cache->forget((int) $block->page_id);
    }
}
