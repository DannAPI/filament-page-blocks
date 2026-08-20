<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = ['name', 'handle', 'suppressed_admin_targets'];

    protected static function booted(): void
    {
        static::deleting(static fn (Menu $menu): bool => ! $menu->isSystem());
    }

    public function getTable(): string
    {
        return (string) config('filament-page-blocks.tables.menus', parent::getTable());
    }

    public function isSystem(): bool
    {
        $handles = static::systemHandles();

        return in_array((string) $this->getAttribute('handle'), $handles, true)
            || in_array((string) $this->getRawOriginal('handle'), $handles, true);
    }

    protected function casts(): array
    {
        return [
            'suppressed_admin_targets' => 'array',
        ];
    }

    /** @return array<int, string> */
    public function suppressedAdminTargets(): array
    {
        return array_values(array_unique(array_filter(
            (array) $this->getAttribute('suppressed_admin_targets'),
            static fn (mixed $target): bool => is_string($target) && $target !== '',
        )));
    }

    public function suppressAdminTarget(string $target): void
    {
        if ($target === '' || in_array($target, $this->suppressedAdminTargets(), true)) {
            return;
        }

        $this->forceFill([
            'suppressed_admin_targets' => [...$this->suppressedAdminTargets(), $target],
        ])->saveQuietly();
    }

    public function restoreAdminTarget(string $target): void
    {
        $targets = array_values(array_filter(
            $this->suppressedAdminTargets(),
            static fn (string $suppressed): bool => $suppressed !== $target,
        ));

        if ($targets === $this->suppressedAdminTargets()) {
            return;
        }

        $this->forceFill(['suppressed_admin_targets' => $targets])->saveQuietly();
    }

    /** @return array<int, string> */
    public static function systemHandles(): array
    {
        return array_values(array_unique(array_filter([
            config('filament-page-blocks.menus.admin.handle', 'admin'),
            config('filament-page-blocks.menus.header', 'header'),
            config('filament-page-blocks.menus.footer', 'footer'),
        ], static fn (mixed $handle): bool => is_string($handle) && $handle !== '')));
    }

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        /** @var class-string<MenuItem> $model */
        $model = config('filament-page-blocks.models.menu_item', MenuItem::class);

        return $this->hasMany($model)->whereNull('parent_id')->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<MenuItem, $this> */
    public function allItems(): HasMany
    {
        /** @var class-string<MenuItem> $model */
        $model = config('filament-page-blocks.models.menu_item', MenuItem::class);

        return $this->hasMany($model)->orderBy('sort')->orderBy('id');
    }
}
