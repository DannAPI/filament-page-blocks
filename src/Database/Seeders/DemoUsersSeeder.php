<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('filament-page-blocks.seeders.demo_users.enabled', true)) {
            return;
        }

        if (app()->environment('production') && ! config('filament-page-blocks.seeders.demo_users.allow_production', false)) {
            $this->command?->warn('Demo admin users were not seeded in production.');

            return;
        }

        $model = config('filament-page-blocks.seeders.demo_users.model', 'App\\Models\\User');
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('filament-page-blocks.seeders.demo_users.model must be an Eloquent model class.');
        }

        foreach ((array) config('filament-page-blocks.seeders.demo_users.users', []) as $attributes) {
            if (! is_array($attributes)) {
                continue;
            }

            $name = $attributes['name'] ?? null;
            $email = $attributes['email'] ?? null;
            $password = $attributes['password'] ?? null;
            if (! is_string($name) || ! is_string($email) || ! is_string($password) || $name === '' || $email === '' || $password === '') {
                throw new RuntimeException('Each demo user requires non-empty name, email, and password strings.');
            }

            /** @var Model|null $existing */
            $existing = $model::query()->where('email', $email)->first();
            if ($existing !== null) {
                if (($attributes['system'] ?? false) === true) {
                    $existing->forceFill(['is_system' => true])->save();
                }

                continue;
            }

            /** @var Model $user */
            $user = new $model;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_system' => ($attributes['system'] ?? false) === true,
            ])->save();
        }
    }
}
