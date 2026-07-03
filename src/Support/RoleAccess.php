<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Contracts\Auth\Authenticatable;

final class RoleAccess
{
    public function allows(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'hasPageBlocksPermission')
            && (bool) $user->hasPageBlocksPermission($permission);
    }
}
