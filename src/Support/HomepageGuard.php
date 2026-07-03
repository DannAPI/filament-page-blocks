<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Models\Page;
use Illuminate\Validation\ValidationException;

final class HomepageGuard
{
    public function anotherHomepageExists(?Page $page = null): bool
    {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);
        $query = $model::query()->where('is_homepage', true);

        if ($page?->exists) {
            $query->whereKeyNot($page->getKey());
        }

        return $query->exists();
    }

    public function ensureAvailable(?Page $page = null): void
    {
        if (! $this->anotherHomepageExists($page)) {
            return;
        }

        throw ValidationException::withMessages([
            'is_homepage' => 'Another page is already configured as the homepage.',
        ]);
    }
}
