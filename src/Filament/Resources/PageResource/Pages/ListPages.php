<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\PageResource\Pages;

use DannAPI\FilamentPageBlocks\Exceptions\InvalidPageOrderException;
use DannAPI\FilamentPageBlocks\Filament\Resources\PageResource;
use DannAPI\FilamentPageBlocks\Support\PageOrderManager;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /** @param array<int|string> $order */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        try {
            app(PageOrderManager::class)->reorder($order, $draggedRecordKey);
        } catch (InvalidPageOrderException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }
}
