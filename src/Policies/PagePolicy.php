<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Policies;

use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Support\RoleAccess;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class PagePolicy
{
    public function __construct(private RoleAccess $access) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'pages.viewAny');
    }

    public function view(Authenticatable $user, Page $page): bool
    {
        return $this->access->allows($user, 'pages.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'pages.create');
    }

    public function update(Authenticatable $user, Page $page): bool
    {
        return $this->access->allows($user, 'pages.update');
    }

    public function delete(Authenticatable $user, Page $page): bool
    {
        return ! $page->is_system && $this->access->allows($user, 'pages.delete');
    }

    public function restore(Authenticatable $user, Page $page): bool
    {
        return $this->access->allows($user, 'pages.restore');
    }

    public function forceDelete(Authenticatable $user, Page $page): bool
    {
        return ! $page->is_system && $this->access->allows($user, 'pages.forceDelete');
    }

    public function publish(Authenticatable $user, Page $page): bool
    {
        return $this->access->allows($user, 'pages.publish');
    }
}
