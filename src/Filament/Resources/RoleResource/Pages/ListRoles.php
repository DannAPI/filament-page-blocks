<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
