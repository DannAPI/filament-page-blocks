<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['permissions'] = RoleResource::flattenPermissionGroups(
            is_array($data['permission_groups'] ?? null) ? $data['permission_groups'] : [],
        );
        unset($data['permission_groups']);

        return $data;
    }
}
