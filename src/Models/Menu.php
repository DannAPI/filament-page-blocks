<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = ['name', 'handle'];

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
