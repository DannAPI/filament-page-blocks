<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Exceptions\InvalidPageOrderException;
use DannAPI\FilamentPageBlocks\Models\Page;
use Illuminate\Support\Facades\DB;

final class PageOrderManager
{
    public function markSystemManaged(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            if (! $page->is_system) {
                $page->forceFill(['is_system' => true])->save();
            }

            $this->normalizePartitions($page);
        });
    }

    /** @param array<int|string> $order */
    public function reorder(array $order, int|string|null $draggedRecordKey): void
    {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);
        $pages = $model::query()->orderBy('sort')->orderBy('id')->get(['id', 'is_system']);
        $currentOrder = $pages->modelKeys();
        $newOrder = array_values($order);

        if (count($newOrder) !== count($currentOrder) || array_diff($newOrder, $currentOrder) !== [] || array_diff($currentOrder, $newOrder) !== []) {
            throw new InvalidPageOrderException('Clear search and filters before reordering pages.');
        }

        $draggedPage = $pages->firstWhere($pages->first()?->getKeyName() ?? 'id', $draggedRecordKey);
        if ($draggedPage === null || $draggedPage->is_system) {
            throw new InvalidPageOrderException('System-managed pages cannot be moved.');
        }

        $systemPageKeys = array_map('strval', $pages->where('is_system', true)->modelKeys());
        $newSystemPageKeys = array_map('strval', array_slice($newOrder, 0, count($systemPageKeys)));
        if ($newSystemPageKeys !== $systemPageKeys) {
            throw new InvalidPageOrderException('System-managed pages must remain at the top in creation order.');
        }

        DB::transaction(function () use ($model, $newOrder): void {
            foreach ($newOrder as $index => $pageId) {
                $model::query()->whereKey($pageId)->update(['sort' => $index + 1]);
            }
        });
    }

    private function normalizePartitions(Page $page): void
    {
        /** @var class-string<Page> $model */
        $model = $page::class;
        $systemPageIds = $model::query()
            ->where('is_system', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck($page->getKeyName());
        $manualPageIds = $model::query()
            ->where('is_system', false)
            ->orderBy('sort')
            ->orderBy('id')
            ->pluck($page->getKeyName());

        $systemPageIds
            ->concat($manualPageIds)
            ->values()
            ->each(static function (int|string $pageId, int $index) use ($model): void {
                $model::query()->whereKey($pageId)->update(['sort' => $index + 1]);
            });
    }
}
