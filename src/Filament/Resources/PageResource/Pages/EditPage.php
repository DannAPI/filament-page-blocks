<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\PageResource\Pages;

use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Filament\Resources\PageResource;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Support\HomepageGuard;
use DannAPI\FilamentPageBlocks\Support\PageBlockSynchronizer;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

final class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /** @var array<int, array{type?: mixed, data?: mixed}> */
    private array $pendingBlocks = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->getRecord();
        $data['content_blocks'] = app(PageBlockSynchronizer::class)->toBuilderState($page);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingBlocks = is_array($data['content_blocks'] ?? null) ? $data['content_blocks'] : [];
        unset($data['content_blocks']);
        $newStatus = PageStatus::tryFrom((string) ($data['status'] ?? ''));
        /** @var Page $page */
        $page = $this->getRecord();
        if (in_array($newStatus, [PageStatus::Published, PageStatus::Scheduled], true) && $newStatus !== $page->status) {
            Gate::authorize('publish', $page);
        }
        if ((bool) ($data['is_homepage'] ?? false)) {
            app(HomepageGuard::class)->ensureAvailable($page);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Page $page */
        $page = $this->getRecord();
        app(PageBlockSynchronizer::class)->sync($page, $this->pendingBlocks, systemManaged: false);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()];
    }
}
