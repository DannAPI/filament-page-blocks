<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListGeneralInfo extends ListRecords
{
    protected static string $resource = GeneralInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
