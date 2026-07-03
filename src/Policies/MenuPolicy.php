<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Policies;

use DannAPI\FilamentPageBlocks\Models\Menu;
use DannAPI\FilamentPageBlocks\Support\RoleAccess;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class MenuPolicy
{
    public function __construct(private RoleAccess $access) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'menus.viewAny');
    }

    public function view(Authenticatable $user, Menu $menu): bool
    {
        return $this->access->allows($user, 'menus.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'menus.create');
    }

    public function update(Authenticatable $user, Menu $menu): bool
    {
        return $this->access->allows($user, 'menus.update');
    }

    public function delete(Authenticatable $user, Menu $menu): bool
    {
        return ! $menu->isSystem() && $this->access->allows($user, 'menus.delete');
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'menus.delete');
    }
}
