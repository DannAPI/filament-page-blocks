<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models;

use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Exceptions\SystemPageMutationException;
use DannAPI\FilamentPageBlocks\Support\HomepageGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'slug', 'status', 'template', 'is_homepage', 'published_at', 'seo_title', 'seo_description'];

    protected $attributes = [
        'template' => 'default',
        'is_homepage' => false,
    ];

    protected static function booted(): void
    {
        static::creating(static function (Page $page): void {
            if ((int) $page->sort === 0) {
                $page->sort = ((int) static::query()->max('sort')) + 1;
            }
        });

        static::saving(static function (Page $page): void {
            if (blank($page->getAttribute('template'))) {
                $page->template = (string) config('filament-page-blocks.default_template', 'default');
            }
            if ($page->getAttribute('is_homepage') === null) {
                $page->is_homepage = false;
            }

            $status = $page->status instanceof PageStatus
                ? $page->status
                : PageStatus::tryFrom((string) $page->getAttribute('status'));
            $originalStatus = PageStatus::tryFrom((string) $page->getRawOriginal('status'));

            if ($status === PageStatus::Published) {
                if (! $page->exists || $originalStatus !== PageStatus::Published || $page->published_at === null) {
                    $page->published_at = now();
                }
            } elseif ($status !== PageStatus::Scheduled) {
                $page->published_at = null;
            }

            if ($page->is_homepage) {
                app(HomepageGuard::class)->ensureAvailable($page);
            }
        });

        static::deleting(static function (Page $page): void {
            if ($page->is_system) {
                throw SystemPageMutationException::deletion();
            }

            if ($page->is_homepage) {
                $page->is_homepage = false;
                $page->saveQuietly();
            }
        });
    }

    public function getTable(): string
    {
        return (string) config('filament-page-blocks.tables.pages', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'is_homepage' => 'boolean',
            'is_system' => 'boolean',
            'sort' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<PageBlock, $this> */
    public function blocks(): HasMany
    {
        /** @var class-string<PageBlock> $model */
        $model = config('filament-page-blocks.models.page_block', PageBlock::class);

        return $this->hasMany($model)->orderBy('sort');
    }

    /** @param Builder<Page> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->where('status', PageStatus::Published->value)
                    ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()));
            })->orWhere(function (Builder $query): void {
                $query->where('status', PageStatus::Scheduled->value)->whereNotNull('published_at')->where('published_at', '<=', now());
            });
        });
    }

    public function isPublished(): bool
    {
        return ($this->status === PageStatus::Published && ($this->published_at === null || $this->published_at->isPast()))
            || ($this->status === PageStatus::Scheduled && $this->published_at !== null && $this->published_at->isPast());
    }
}
