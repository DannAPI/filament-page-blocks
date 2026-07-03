<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\PageResource\Pages;

use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Filament\Resources\PageResource;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Support\HomepageGuard;
use DannAPI\FilamentPageBlocks\Support\PageBlockSynchronizer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

final class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /** @var array<int, array{type?: mixed, data?: mixed}> */
    private array $pendingBlocks = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingBlocks = is_array($data['content_blocks'] ?? null) ? $data['content_blocks'] : [];
        unset($data['content_blocks']);

        if ((bool) ($data['is_homepage'] ?? false)) {
            app(HomepageGuard::class)->ensureAvailable();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Page $page */
        $page = $this->getRecord();
        if (in_array($page->status, [PageStatus::Published, PageStatus::Scheduled], true)) {
            Gate::authorize('publish', $page);
        }
        app(PageBlockSynchronizer::class)->sync($page, $this->pendingBlocks, systemManaged: false);
    }
}
