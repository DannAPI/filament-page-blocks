<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models\Concerns;

use DannAPI\FilamentPageBlocks\Models\Role;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;

trait HasPageBlocksRoles
{
    protected static function bootHasPageBlocksRoles(): void
    {
        static::deleting(static function (Model $user): void {
            if ((bool) $user->getAttribute('is_system')) {
                throw new RuntimeException('System users cannot be deleted.');
            }
        });
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        /** @var class-string<Role> $model */
        $model = config('filament-page-blocks.models.role', Role::class);

        return $this->belongsToMany(
            $model,
            (string) config('filament-page-blocks.tables.role_user', 'role_user'),
        );
    }

    public function hasPageBlocksRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function hasPageBlocksPermission(string $permission): bool
    {
        return $this->roles->contains(static fn (Role $role): bool => $role->grants($permission));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPageBlocksPermission('panel.access');
    }
}
