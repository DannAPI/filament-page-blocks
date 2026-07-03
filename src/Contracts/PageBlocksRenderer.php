<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Contracts;

use DannAPI\FilamentPageBlocks\Models\Page;
use Illuminate\Contracts\View\View;

interface PageBlocksRenderer
{
    public function render(Page $page): View;
}
