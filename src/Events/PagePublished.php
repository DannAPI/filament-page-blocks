<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Events;

use DannAPI\FilamentPageBlocks\Models\Page;
use Illuminate\Foundation\Events\Dispatchable;

final class PagePublished
{
    use Dispatchable;

    public function __construct(public readonly Page $page) {}
}
