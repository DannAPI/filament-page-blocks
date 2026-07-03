<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\UserResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['password']) && is_string($data['password']) && $data['password'] !== '') {
            $data['password'] = Hash::make($data['password']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
