<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Support\Facades\Storage;

final class Media
{
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk((string) config('filament-page-blocks.media.disk', 'public'))->url($path);
    }
}
