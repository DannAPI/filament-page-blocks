<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Events\PageBlocksReordered;
use DannAPI\FilamentPageBlocks\Exceptions\InvalidBlockException;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Models\PageBlock;
use DannAPI\FilamentPageBlocks\Registry\BlockRegistry;
use DannAPI\FilamentPageBlocks\Registry\PageTemplateRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class PageBlockSynchronizer
{
    public function __construct(
        private BlockRegistry $registry,
        private PageTemplateRegistry $templates,
        private SystemBlockReuseGuard $systemBlockReuseGuard,
    ) {}

    /** @param array<int, array{type?: mixed, data?: mixed}> $items */
    public function sync(Page $page, array $items, bool $systemManaged = true): void
    {
        if ($systemManaged) {
            app(PageOrderManager::class)->markSystemManaged($page);
        }

        DB::transaction(function () use ($page, $items, $systemManaged): void {
            $page->load('blocks');
            $oldOrder = $page->blocks->filter(fn (PageBlock $block): bool => $this->registry->has($block->type))->pluck('key')->values()->all();
            $existing = $page->blocks->keyBy('key');
            $seen = [];
            $newOrder = [];
            $template = $this->templates->get($page->template);
            $systemTypes = $page->is_system ? [] : $this->systemBlockReuseGuard->usedTypes();

            foreach (array_values($items) as $sort => $item) {
                $type = is_string($item['type'] ?? null) ? $item['type'] : '';
                if (! $this->registry->has($type)) {
                    throw new InvalidBlockException("Cannot save unknown block [{$type}].");
                }
                $class = $this->registry->get($type);
                if (! $template->allows($type, $class) || ! $class::authorize($page)) {
                    throw new InvalidBlockException("Block [{$type}] is not allowed for template [{$page->template}].");
                }

                $data = is_array($item['data'] ?? null) ? $item['data'] : [];
                $candidate = is_string($data['__key'] ?? null) ? $data['__key'] : '';
                $key = Str::isUuid($candidate) && ! isset($seen[$candidate]) ? $candidate : (string) Str::uuid();
                $seen[$key] = true;
                $newOrder[] = $key;
                /** @var PageBlock|null $existingBlock */
                $existingBlock = $existing->get($key);
                if (
                    ! $page->is_system
                    && $this->systemBlockReuseGuard->isRestricted($type, $class, $systemTypes)
                    && ($existingBlock === null || $existingBlock->type !== $type)
                ) {
                    throw new InvalidBlockException("System block [{$type}] cannot be reused.");
                }
                $visible = (bool) ($data['__visible'] ?? true);
                unset($data['__key'], $data['__visible'], $data['__system']);
                $normalized = $class::normalize($data);

                /** @var PageBlock $block */
                $block = $existingBlock ?? $page->blocks()->make(['key' => $key]);
                $block->fill([
                    'type' => $type,
                    'data' => $normalized,
                    'sort' => $sort,
                    'is_visible' => $visible,
                    'is_system' => $existingBlock?->is_system ?? $systemManaged,
                ]);
                $block->save();
            }

            $registeredTypes = array_keys($this->registry->all());
            $missingBlocks = $page->blocks()
                ->whereIn('type', $registeredTypes)
                ->whereNotIn('key', $newOrder)
                ->get();

            if (! $systemManaged && $missingBlocks->contains(static fn (PageBlock $block): bool => $block->is_system)) {
                throw new InvalidBlockException('System blocks cannot be deleted. Disable Visible to hide them.');
            }

            foreach ($missingBlocks as $missingBlock) {
                if ($missingBlock->is_system) {
                    $missingBlock->deleteFromSystemSynchronizer();

                    continue;
                }

                if (! $systemManaged) {
                    $missingBlock->delete();
                }
            }

            $page->blocks()->whereNotIn('key', $newOrder)->orderBy('sort')->get()
                ->each(static function (PageBlock $block, int $offset) use ($items): void {
                    $block->update(['sort' => count($items) + $offset]);
                });
            $page->unsetRelation('blocks');
            if ($oldOrder !== $newOrder) {
                PageBlocksReordered::dispatch($page, $newOrder);
            }
        });
    }

    /** @return array<int, array{type: string, data: array<string, mixed>}> */
    public function toBuilderState(Page $page): array
    {
        return $page->blocks()->get()
            ->filter(fn (PageBlock $block): bool => $this->registry->has($block->type))
            ->map(static fn (PageBlock $block): array => [
                'type' => $block->type,
                'data' => array_merge($block->data, [
                    '__key' => $block->key,
                    '__visible' => $block->is_visible,
                    '__system' => $block->is_system,
                ]),
            ])->values()->all();
    }
}
