<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models\Concerns;

use DannAPI\FilamentPageBlocks\Support\SortPositionManager;
use Illuminate\Database\Eloquent\Model;

trait HasSortablePosition
{
    public static function bootHasSortablePosition(): void
    {
        static::creating(static function (Model $record): void {
            app(SortPositionManager::class)->prepareForCreate($record);
        });

        static::updating(static function (Model $record): void {
            app(SortPositionManager::class)->prepareForUpdate($record);
        });
    }
}
