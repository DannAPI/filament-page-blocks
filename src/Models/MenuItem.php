<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models;

use DannAPI\FilamentPageBlocks\Enums\MenuLinkType;
use DannAPI\FilamentPageBlocks\Support\MenuUrlResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'page_id',
        'label',
        'link_type',
        'url',
        'icon',
        'target',
        'sort',
        'is_visible',
    ];

    protected static function booted(): void
    {
        static::creating(static function (MenuItem $item): void {
            if ($item->menu_id === null && $item->parent_id !== null) {
                $item->menu_id = static::query()->whereKey($item->parent_id)->value('menu_id');
            }
        });
    }

    public function getTable(): string
    {
        return (string) config('filament-page-blocks.tables.menu_items', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'link_type' => MenuLinkType::class,
            'sort' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        /** @var class-string<Menu> $model */
        $model = config('filament-page-blocks.models.menu', Menu::class);

        return $this->belongsTo($model);
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);

        return $this->belongsTo($model);
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function href(): string
    {
        return app(MenuUrlResolver::class)->resolve($this);
    }

    public function isActive(Page $currentPage): bool
    {
        if (
            $this->link_type === MenuLinkType::Page
            && $this->page_id !== null
            && (string) $this->page_id === (string) $currentPage->getKey()
        ) {
            return true;
        }

        if ($this->link_type === MenuLinkType::Custom) {
            $href = rtrim($this->href(), '/');
            if ($href !== '#' && $href === rtrim(url()->current(), '/')) {
                return true;
            }
        }

        return $this->children->contains(static fn (MenuItem $child): bool => $child->isActive($currentPage));
    }
}
