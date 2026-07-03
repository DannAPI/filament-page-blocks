<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Commands;

use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Registry\BlockRegistry;
use DannAPI\FilamentPageBlocks\Support\PageBlockSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ImportLegacyPageBlocksCommand extends Command
{
    protected $signature = 'page-blocks:import-legacy
        {--dry-run : Inspect and validate without writing}
        {--connection= : Legacy database connection}
        {--page= : Import one legacy page route or ID}
        {--force : Required for writes}';

    protected $description = 'Safely import configured legacy page and block data';

    public function handle(PageBlockSynchronizer $synchronizer, BlockRegistry $registry): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('force')) {
            $this->components->error('Use --dry-run first. Actual writes require --force.');

            return self::INVALID;
        }
        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $legacy = DB::connection($connection);
        $selector = $this->option('page');
        $query = $legacy->table('pages')->orderBy('id');
        if (is_string($selector) && $selector !== '') {
            $query->where(fn ($query) => $query->where('id', ctype_digit($selector) ? (int) $selector : -1)->orWhere('route', $selector));
        }
        $mappings = (array) config('filament-page-blocks.legacy_import.types', []);
        $count = 0;

        foreach ($query->cursor() as $legacyPage) {
            $items = [];
            foreach ($legacy->table('page_blocks')->where('page_id', $legacyPage->id)->orderBy('sorting')->orderBy('id')->get() as $link) {
                $mapping = $mappings[$link->blockable_type] ?? null;
                if (! is_array($mapping) || ! isset($mapping['type'], $mapping['table'])) {
                    Log::warning('Legacy page block skipped: missing mapping.', ['type' => $link->blockable_type, 'id' => $link->id]);

                    continue;
                }
                if (! $registry->has((string) $mapping['type'])) {
                    Log::warning('Legacy page block skipped: target type is not registered.', ['type' => $mapping['type'], 'id' => $link->id]);

                    continue;
                }
                $payload = $legacy->table((string) $mapping['table'])->where('id', $link->blockable_id)->first();
                if ($payload === null) {
                    Log::warning('Legacy page block payload missing.', ['id' => $link->id]);

                    continue;
                }
                $data = Arr::except((array) $payload, ['id', 'created_at', 'updated_at']);
                $transform = $mapping['transform'] ?? null;
                if (is_string($transform) && class_exists($transform)) {
                    $transform = app($transform);
                }
                if (is_callable($transform)) {
                    $data = $transform($data, $legacyPage);
                }
                if (! is_array($data)) {
                    Log::warning('Legacy page block skipped: transformer did not return an array.', ['type' => $mapping['type'], 'id' => $link->id]);

                    continue;
                }
                $items[] = ['type' => (string) $mapping['type'], 'data' => array_merge($data, ['__key' => (string) Str::uuid(), '__visible' => (bool) $link->display])];
            }
            $this->line(sprintf('%s: %d block(s)', $legacyPage->route, count($items)));
            if (! $dryRun) {
                /** @var class-string<Page> $model */
                $model = config('filament-page-blocks.models.page', Page::class);
                $page = $model::query()->updateOrCreate(['slug' => ltrim((string) $legacyPage->route, '/') ?: 'home'], [
                    'title' => (string) ($legacyPage->title ?: $legacyPage->page),
                    'status' => $legacyPage->isActive ? PageStatus::Published : PageStatus::Draft,
                    'template' => (string) config('filament-page-blocks.default_template', 'default'),
                    'is_homepage' => $legacyPage->route === '/',
                    'seo_title' => $legacyPage->metaTitle,
                    'seo_description' => $legacyPage->metaDescription,
                ]);
                $synchronizer->sync($page, $items);
            }
            $count++;
        }
        $this->components->info(($dryRun ? 'Dry run complete' : 'Import complete').": {$count} page(s). See the log for skipped blocks.");

        return self::SUCCESS;
    }
}
