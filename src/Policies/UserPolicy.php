<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Policies;

use DannAPI\FilamentPageBlocks\Support\RoleAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final readonly class UserPolicy
{
    public function __construct(private RoleAccess $access) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'users.viewAny');
    }

    public function view(Authenticatable $user, Model $record): bool
    {
        return $this->access->allows($user, 'users.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'users.create');
    }

    public function update(Authenticatable $user, Model $record): bool
    {
        return $this->access->allows($user, 'users.update');
    }

    public function delete(Authenticatable $user, Model $record): bool
    {
        return ! (bool) $record->getAttribute('is_system')
            && $this->access->allows($user, 'users.delete');
    }
}
