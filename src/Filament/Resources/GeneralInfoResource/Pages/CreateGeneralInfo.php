<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateGeneralInfo extends CreateRecord
{
    protected static string $resource = GeneralInfoResource::class;
}
