<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource;
use Filament\Resources\Pages\EditRecord;

final class EditGeneralInfo extends EditRecord
{
    protected static string $resource = GeneralInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
