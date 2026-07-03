<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Contracts;

use DannAPI\FilamentPageBlocks\Models\Page;

interface FrontendDataProvider
{
    /** @return array<string, mixed> */
    public function data(Page $page): array;
}
