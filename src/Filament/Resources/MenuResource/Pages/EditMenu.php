<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource;
use DannAPI\FilamentPageBlocks\Models\Menu;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function afterSave(): void
    {
        if ($this->getRecord()->getAttribute('handle') !== config('filament-page-blocks.menus.admin.handle', 'admin')) {
            return;
        }

        $this->redirect(
            static::getResource()::getUrl('edit', ['record' => $this->getRecord()]),
            navigate: true,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(static fn (Menu $record): bool => ! $record->isSystem()),
        ];
    }
}
