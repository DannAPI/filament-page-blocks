<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Policies;

use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use DannAPI\FilamentPageBlocks\Support\RoleAccess;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class GeneralInfoPolicy
{
    public function __construct(private RoleAccess $access) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'general_info.viewAny');
    }

    public function view(Authenticatable $user, GeneralInfo $generalInfo): bool
    {
        return $this->access->allows($user, 'general_info.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->access->allows($user, 'general_info.create');
    }

    public function update(Authenticatable $user, GeneralInfo $generalInfo): bool
    {
        return $this->access->allows($user, 'general_info.update');
    }

    public function delete(Authenticatable $user, GeneralInfo $generalInfo): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
