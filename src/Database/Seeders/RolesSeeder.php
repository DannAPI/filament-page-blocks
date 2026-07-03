<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Database\Seeders;

use DannAPI\FilamentPageBlocks\Models\Role;
use DannAPI\FilamentPageBlocks\Registry\PermissionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

final class RolesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var class-string<Role> $roleModel */
        $roleModel = config('filament-page-blocks.models.role', Role::class);

        $admin = $roleModel::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'permissions' => ['*'], 'is_system' => true],
        );
        $manager = $roleModel::query()->firstOrCreate(
            ['slug' => 'manager'],
            ['name' => 'Manager', 'permissions' => app(PermissionRegistry::class)->keys(), 'is_system' => true],
        );
        $user = $roleModel::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'permissions' => [], 'is_system' => true],
        );

        $admin->forceFill(['name' => 'Admin', 'permissions' => ['*'], 'is_system' => true])->save();
        $user->forceFill(['name' => 'User', 'permissions' => [], 'is_system' => true])->save();

        $allPermissions = app(PermissionRegistry::class)->keys();
        $userPermissions = array_keys((array) config('filament-page-blocks.authorization.permissions.Users', []));
        $permissionsBeforeUsersResource = array_diff($allPermissions, $userPermissions);
        $managerPermissions = is_array($manager->permissions) ? $manager->permissions : [];
        $managerStillHadFullAccess = array_diff($permissionsBeforeUsersResource, $managerPermissions) === [];
        if ($managerStillHadFullAccess) {
            $manager->permissions = array_values(array_unique([...$managerPermissions, ...$userPermissions]));
        }
        $manager->forceFill(['name' => 'Manager', 'is_system' => true])->save();

        /** @var class-string<Model> $userModel */
        $userModel = config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');
        foreach ((array) config('filament-page-blocks.seeders.demo_users.users', []) as $attributes) {
            if (! is_array($attributes) || ! is_string($attributes['email'] ?? null) || ! is_string($attributes['role'] ?? null)) {
                continue;
            }
            $account = $userModel::query()->where('email', $attributes['email'])->first();
            $role = $roleModel::query()->where('slug', $attributes['role'])->first();
            if ($account !== null && $role !== null && method_exists($account, 'roles')) {
                $account->roles()->sync([$role->getKey()]);
            }
        }
    }
}
