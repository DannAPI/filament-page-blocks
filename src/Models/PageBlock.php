<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PageBlock extends Model
{
    protected $fillable = ['page_id', 'key', 'type', 'data', 'sort', 'is_visible', 'is_system'];

    private bool $systemDeletionAllowed = false;

    protected static function booted(): void
    {
        static::deleting(static function (PageBlock $block): void {
            if ($block->is_system && ! $block->systemDeletionAllowed) {
                throw new RuntimeException('System blocks cannot be deleted. Hide the block instead.');
            }
        });
    }

    public function getTable(): string
    {
        return (string) config('filament-page-blocks.tables.page_blocks', parent::getTable());
    }

    protected function casts(): array
    {
        return ['data' => 'array', 'sort' => 'integer', 'is_visible' => 'boolean', 'is_system' => 'boolean'];
    }

    public function deleteFromSystemSynchronizer(): ?bool
    {
        $this->systemDeletionAllowed = true;

        try {
            return $this->delete();
        } finally {
            $this->systemDeletionAllowed = false;
        }
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        /** @var class-string<Page> $model */
        $model = config('filament-page-blocks.models.page', Page::class);

        return $this->belongsTo($model);
    }
}
