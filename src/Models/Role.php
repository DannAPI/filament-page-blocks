<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'permissions', 'is_system'];

    protected static function booted(): void
    {
        static::deleting(static function (Role $role): void {
            if ($role->is_system) {
                throw new RuntimeException('System roles cannot be deleted.');
            }
        });
    }

    public function getTable(): string
    {
        return (string) config('filament-page-blocks.tables.roles', parent::getTable());
    }

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    /** @return BelongsToMany<Model, $this> */
    public function users(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');

        return $this->belongsToMany(
            $model,
            (string) config('filament-page-blocks.tables.role_user', 'role_user'),
        );
    }

    public function grants(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
