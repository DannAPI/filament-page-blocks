<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Policies;

use DannAPI\FilamentPageBlocks\Models\Role;
use DannAPI\FilamentPageBlocks\Support\RoleAccess;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class RolePolicy
{
    public function __construct(private RoleAccess $access) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'roles.viewAny');
    }

    public function view(Authenticatable $user, Role $role): bool
    {
        return $this->access->allows($user, 'roles.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'roles.create');
    }

    public function update(Authenticatable $user, Role $role): bool
    {
        return $this->access->allows($user, 'roles.update');
    }

    public function delete(Authenticatable $user, Role $role): bool
    {
        return ! $role->is_system && $this->access->allows($user, 'roles.delete');
    }
}
