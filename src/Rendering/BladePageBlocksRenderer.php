<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Rendering;

use DannAPI\FilamentPageBlocks\Contracts\FrontendDataProvider;
use DannAPI\FilamentPageBlocks\Contracts\PageBlocksRenderer;
use DannAPI\FilamentPageBlocks\Data\BlockData;
use DannAPI\FilamentPageBlocks\Data\BlockRelationDefinition;
use DannAPI\FilamentPageBlocks\Exceptions\UnknownBlockTypeException;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Models\PageBlock;
use DannAPI\FilamentPageBlocks\Registry\BlockRegistry;
use DannAPI\FilamentPageBlocks\Registry\PageTemplateRegistry;
use DannAPI\FilamentPageBlocks\Support\BlockRelationResolver;
use Illuminate\Contracts\View\View;

final readonly class BladePageBlocksRenderer implements PageBlocksRenderer
{
    public function __construct(
        private BlockRegistry $blocks,
        private PageTemplateRegistry $templates,
        private PageCache $cache,
        private FrontendDataProvider $frontendData,
    ) {}

    public function render(Page $page): View
    {
        $page->loadMissing('blocks');
        $frontendData = $this->frontendData->data($page);
        $generalInfo = $frontendData['generalInfo'] ?? null;
        $content = $this->cache->remember($page, fn (): string => $this->renderBlocks($page, $generalInfo));
        $template = $this->templates->get($page->template);
        $layout = $template->getLayout();

        return view($layout, [
            ...$frontendData,
            'page' => $page,
            'content' => $content,
            'template' => $template,
        ]);
    }

    private function renderBlocks(Page $page, mixed $generalInfo): string
    {
        return $page->blocks
            ->filter(static fn (PageBlock $block): bool => $block->is_visible)
            ->map(function (PageBlock $block) use ($page, $generalInfo): string {
                if (! $this->blocks->has($block->type)) {
                    if (config('filament-page-blocks.rendering.unknown_blocks', 'skip') === 'throw') {
                        throw UnknownBlockTypeException::for($block->type);
                    }

                    return '';
                }
                $class = $this->blocks->get($block->type);
                if (! $class::authorize($page)) {
                    return '';
                }
                $values = $class::normalize($block->data);

                $relations = [];
                foreach ($class::form() as $component) {
                    $definition = $component->getMeta('filament-page-blocks.relation');
                    if ($definition instanceof BlockRelationDefinition) {
                        $relations[$definition->name] = $definition;
                    }
                }

                return view($class::view(), [
                    'data' => new BlockData($values, new BlockRelationResolver($values, $relations)),
                    'page' => $page,
                    'block' => $block,
                    'generalInfo' => $generalInfo,
                ])->render();
            })
            ->implode('');
    }
}
