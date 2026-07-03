<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Contracts\BlockContract;
use DannAPI\FilamentPageBlocks\Models\PageBlock;
use Illuminate\Database\Eloquent\Builder;

final class SystemBlockReuseGuard
{
    public function enabled(): bool
    {
        return (bool) config('filament-page-blocks.system_blocks.prevent_reuse', true);
    }

    /** @return array<int, string> */
    public function usedTypes(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        /** @var class-string<PageBlock> $model */
        $model = config('filament-page-blocks.models.page_block', PageBlock::class);

        return $model::query()
            ->whereHas('page', static fn (Builder $query): Builder => $query->where('is_system', true))
            ->distinct()
            ->pluck('type')
            ->filter(static fn (mixed $type): bool => is_string($type) && $type !== '')
            ->values()
            ->all();
    }

    /**
     * @param  class-string<BlockContract>  $class
     * @param  array<int, string>|null  $usedTypes
     */
    public function isRestricted(string $type, string $class, ?array $usedTypes = null): bool
    {
        if (! $this->enabled() || $class::isReusable()) {
            return false;
        }

        return in_array($type, $usedTypes ?? $this->usedTypes(), true);
    }
}
