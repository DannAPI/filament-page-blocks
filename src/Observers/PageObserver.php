<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Observers;

use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Events\PagePublished;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Rendering\PageCache;

final readonly class PageObserver
{
    public function __construct(private PageCache $cache) {}

    public function saved(Page $page): void
    {
        $this->cache->forget($page);
        if ($page->wasChanged('status') && $page->status === PageStatus::Published) {
            PagePublished::dispatch($page);
        }
    }

    public function deleted(Page $page): void
    {
        $this->cache->forget($page);
    }

    public function restored(Page $page): void
    {
        $this->cache->forget($page);
    }
}
