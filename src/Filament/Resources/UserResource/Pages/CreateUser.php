<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\UserResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Hash::make((string) $data['password']);

        return $data;
    }
}
