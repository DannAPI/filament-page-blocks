<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Observers;

use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use DannAPI\FilamentPageBlocks\Rendering\PageCache;

final readonly class GeneralInfoObserver
{
    public function __construct(private PageCache $cache) {}

    public function saved(GeneralInfo $generalInfo): void
    {
        $this->cache->forgetAll();
    }

    public function deleted(GeneralInfo $generalInfo): void
    {
        $this->cache->forgetAll();
    }
}
