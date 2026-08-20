<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource;
use DannAPI\FilamentPageBlocks\Support\GeneralInfoStructure;
use Filament\Resources\Pages\EditRecord;

final class EditGeneralInfo extends EditRecord
{
    protected static string $resource = GeneralInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(GeneralInfoStructure::class)->preserve($this->record, $data);
    }
}
