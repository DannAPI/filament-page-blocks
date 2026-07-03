<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Registry;

use DannAPI\FilamentPageBlocks\Contracts\BlockContract;
use DannAPI\FilamentPageBlocks\Exceptions\InvalidBlockException;
use DannAPI\FilamentPageBlocks\Exceptions\UnknownBlockTypeException;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Support\SystemBlockReuseGuard;
use Filament\Forms\Components\Builder\Block as FilamentBlock;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;

final class BlockRegistry
{
    /** @var array<string, class-string<BlockContract>> */
    private array $blocks = [];

    public function __construct(private readonly SystemBlockReuseGuard $systemBlockReuseGuard) {}

    /** @param iterable<class-string<BlockContract>> $blocks */
    public function register(iterable $blocks): self
    {
        foreach ($blocks as $block) {
            if (! is_string($block) || ! is_subclass_of($block, BlockContract::class)) {
                throw new InvalidBlockException('Registered blocks must implement '.BlockContract::class.'.');
            }

            $name = $block::getName();
            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) !== 1) {
                throw new InvalidBlockException("Invalid block identifier [{$name}].");
            }

            if (isset($this->blocks[$name]) && $this->blocks[$name] !== $block) {
                throw new InvalidBlockException("Block identifier [{$name}] is already registered.");
            }

            $this->blocks[$name] = $block;
        }

        return $this;
    }

    /** @return array<string, class-string<BlockContract>> */
    public function all(): array
    {
        return $this->blocks;
    }

    public function has(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    /** @return class-string<BlockContract> */
    public function get(string $name): string
    {
        return $this->blocks[$name] ?? throw UnknownBlockTypeException::for($name);
    }

    /** @return array<FilamentBlock> */
    public function toFilamentBlocks(?Page $page = null, ?string $template = null): array
    {
        $template ??= $page?->template;
        $definition = app(PageTemplateRegistry::class)->get($template);
        $systemTypes = $this->systemBlockReuseGuard->usedTypes();
        $existingCounts = $page?->blocks()
            ->reorder()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all() ?? [];

        $result = [];
        foreach ($this->blocks as $name => $class) {
            if (! $definition->allows($name, $class) || ! $class::authorize($page)) {
                continue;
            }

            $restricted = $this->systemBlockReuseGuard->isRestricted($name, $class, $systemTypes);
            $existingCount = $existingCounts[$name] ?? 0;
            if ($restricted && $existingCount === 0) {
                continue;
            }

            $block = FilamentBlock::make($name)
                ->label(fn (?array $state): string => $class::summary($state ?? []))
                ->icon($class::getIcon())
                ->schema([
                    Hidden::make('__key'),
                    Toggle::make('__visible')->label('Visible')->default(true),
                    ...$class::form(),
                ]);

            if ($restricted) {
                $block->maxItems($existingCount);
            }

            $result[] = $block;
        }

        return $result;
    }
}
