<?php

declare(strict_types=1);

use DannAPI\FilamentPageBlocks\Support\AssetUrlResolver;

if (! function_exists('page_block_asset')) {
    function page_block_asset(mixed $source, mixed $fallback = null): ?string
    {
        return app(AssetUrlResolver::class)->resolve($source, $fallback);
    }
}
