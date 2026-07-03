<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource;
use DannAPI\FilamentPageBlocks\Models\Role;
use DannAPI\FilamentPageBlocks\Registry\PermissionRegistry;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $permissions = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];
        if (($data['slug'] ?? null) === 'admin') {
            $permissions = app(PermissionRegistry::class)->keys();
        }
        if (($data['slug'] ?? null) === 'user') {
            $permissions = [];
        }
        $data['permission_groups'] = RoleResource::permissionGroupsState($permissions);
        unset($data['permissions']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Role $role */
        $role = $this->getRecord();
        $data['permissions'] = RoleResource::flattenPermissionGroups(
            is_array($data['permission_groups'] ?? null) ? $data['permission_groups'] : [],
        );
        unset($data['permission_groups']);
        if ($role->slug === 'admin') {
            $data['permissions'] = ['*'];
        }
        if ($role->slug === 'user') {
            $data['permissions'] = [];
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
